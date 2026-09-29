<?php

namespace App\Jobs\Integrations;

use App\Crm\Contracts\CrmProviderAdapter;
use App\Crm\CrmErrorCategory;
use App\Crm\CrmOperationProcessor;
use App\Crm\CrmProviderException;
use App\Enums\CrmOperationStatus;
use App\Models\CrmLinkedDeal;
use App\Models\CrmOperation;
use App\Models\Integration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessCrmOperation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 120;

    public function __construct(public int $operationId) {}

    public function handle(CrmOperationProcessor $processor, CrmProviderAdapter $adapter): void
    {
        $operation = CrmOperation::query()->findOrFail($this->operationId);
        if (in_array($operation->status, [CrmOperationStatus::Completed, CrmOperationStatus::Failed], true)) {
            return;
        }

        if ($operation->expires_at->isPast()) {
            $this->failOperation($operation, 'A Operação expirou antes de ser enviada ao CRM.');

            return;
        }

        $integration = $operation->integration;
        if (! $integration instanceof Integration || $integration->status !== 'connected') {
            $operation->forceFill([
                'status' => CrmOperationStatus::Pending,
                'failure_message' => 'A Operação aguarda a reconexão do CRM para continuar.',
            ])->save();

            return;
        }

        $attempt = $operation->attempts + 1;
        $operation->forceFill([
            'status' => CrmOperationStatus::Processing,
            'failure_message' => null,
        ])->save();

        try {
            $linkedDeal = $processor->process($operation);
            $this->completeOperation($operation, $attempt, $linkedDeal);
        } catch (CrmProviderException $exception) {
            $this->handleProviderException($operation, $integration, $adapter, $processor, $attempt, $exception);
        } catch (Throwable $exception) {
            report($exception);
            $this->retryOrFail($operation, $attempt, 'Não foi possível concluir a Operação no CRM.');
        }
    }

    private function handleProviderException(
        CrmOperation $operation,
        Integration $integration,
        CrmProviderAdapter $adapter,
        CrmOperationProcessor $processor,
        int $attempt,
        CrmProviderException $exception,
    ): void {
        if ($exception->category === CrmErrorCategory::RateLimit) {
            $operation->forceFill(['status' => CrmOperationStatus::Pending])->save();
            $this->dispatchAgain(max(1, $exception->retryAfterSeconds ?? 1));

            return;
        }

        if ($exception->category === CrmErrorCategory::AccessDenied && $operation->token_refresh_attempts === 0) {
            try {
                $integration = $adapter->renewIntegration($integration);
                $operation->setRelation('integration', $integration);
                $operation->forceFill(['token_refresh_attempts' => 1])->save();
            } catch (CrmProviderException $refreshException) {
                $this->handleRefreshFailure($operation, $integration, $attempt, $refreshException);

                return;
            } catch (Throwable $retryException) {
                report($retryException);
                $this->retryOrFail($operation, $attempt, 'Não foi possível renovar o acesso ao CRM.');

                return;
            }

            try {
                $linkedDeal = $processor->process($operation);
                $this->completeOperation($operation, $attempt, $linkedDeal);
            } catch (CrmProviderException $retryException) {
                $this->handleProviderException($operation, $integration, $adapter, $processor, $attempt, $retryException);
            } catch (Throwable $refreshException) {
                report($refreshException);
                $this->retryOrFail($operation, $attempt, 'Não foi possível concluir a Operação no CRM.');
            }

            return;
        }

        if ($exception->category === CrmErrorCategory::Temporary) {
            $this->retryOrFail($operation, $attempt, $exception->getMessage());

            return;
        }

        if ($exception->category === CrmErrorCategory::AccessDenied) {
            $this->markIntegrationUnavailable($integration);
            $this->waitForReconnect($operation, $attempt);

            return;
        }

        $this->failOperation($operation, $exception->getMessage(), $attempt);
    }

    private function handleRefreshFailure(
        CrmOperation $operation,
        Integration $integration,
        int $attempt,
        CrmProviderException $exception,
    ): void {
        if ($exception->category === CrmErrorCategory::RateLimit) {
            $operation->forceFill(['status' => CrmOperationStatus::Pending])->save();
            $this->dispatchAgain(max(1, $exception->retryAfterSeconds ?? 1));

            return;
        }

        if ($exception->category === CrmErrorCategory::Temporary) {
            $this->retryOrFail($operation, $attempt, $exception->getMessage());

            return;
        }

        if ($exception->category === CrmErrorCategory::AccessDenied) {
            $this->markIntegrationUnavailable($integration);
            $this->waitForReconnect($operation, $attempt);

            return;
        }

        $this->failOperation($operation, $exception->getMessage(), $attempt);
    }

    private function completeOperation(CrmOperation $operation, int $attempt, CrmLinkedDeal $linkedDeal): void
    {
        $operation->forceFill([
            'status' => CrmOperationStatus::Completed,
            'attempts' => $attempt,
            'failure_message' => null,
            'completed_at' => now(),
            'summary' => [
                ...$operation->summary,
                'linked_deal' => [
                    'pipeline_id' => $linkedDeal->pipeline_id,
                    'stage_id' => $linkedDeal->stage_id,
                    'status' => $linkedDeal->status,
                    'value' => $linkedDeal->value,
                    'owner_id' => $linkedDeal->owner_id,
                ],
            ],
        ])->save();
    }

    private function retryOrFail(CrmOperation $operation, int $attempt, string $message): void
    {
        if ($attempt >= 5) {
            $this->failOperation($operation, $message, $attempt);

            return;
        }

        $operation->forceFill([
            'status' => CrmOperationStatus::Pending,
            'attempts' => $attempt,
            'failure_message' => $message,
        ])->save();
        $this->dispatchAgain(min(300, 5 * (2 ** $attempt)));
    }

    private function waitForReconnect(CrmOperation $operation, int $attempt): void
    {
        $operation->forceFill([
            'status' => CrmOperationStatus::Pending,
            'attempts' => $attempt,
            'token_refresh_attempts' => 1,
            'failure_message' => 'O acesso ao CRM foi recusado após a renovação. Reconecte a conta para retomar esta Operação.',
        ])->save();
    }

    private function failOperation(CrmOperation $operation, string $message, ?int $attempt = null): void
    {
        $operation->forceFill([
            'status' => CrmOperationStatus::Failed,
            'attempts' => $attempt ?? $operation->attempts,
            'failure_message' => mb_substr($message, 0, 1000),
            'completed_at' => now(),
        ])->save();
    }

    private function markIntegrationUnavailable(Integration $integration): void
    {
        $integration->forceFill([
            'status' => 'error',
            'status_message' => 'O acesso ao RD Station CRM foi recusado. Reconecte a conta para continuar.',
        ])->save();
    }

    private function dispatchAgain(int $delaySeconds): void
    {
        self::dispatch($this->operationId)
            ->onQueue('crm-operations')
            ->delay(now()->addSeconds($delaySeconds));
    }
}
