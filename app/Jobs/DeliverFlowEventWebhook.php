<?php

namespace App\Jobs;

use App\Exceptions\Flows\FlowEventWebhookDeliveryException;
use App\Models\FlowEventDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Throwable;

class DeliverFlowEventWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    public function __construct(public string $deliveryId) {}

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [1, 5, 30];
    }

    public function handle(): void
    {
        $delivery = FlowEventDelivery::withoutGlobalScopes()->with('webhook')->find($this->deliveryId);
        if ($delivery === null || $delivery->status === 'delivered') {
            return;
        }

        $attempt = $delivery->attempts + 1;
        $delivery->forceFill([
            'attempts' => $attempt,
            'last_attempt_at' => now(),
            'next_attempt_at' => null,
        ])->save();

        $body = json_encode($delivery->payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $signature = 'sha256='.hash_hmac('sha256', $body, (string) $delivery->webhook->secret);

        try {
            $response = Http::connectTimeout(3)
                ->timeout(10)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'X-Event-Signature' => $signature,
                    'X-Event-Id' => (string) $delivery->payload['id'],
                    'X-Event-Name' => (string) $delivery->payload['event'],
                ])
                ->withBody($body, 'application/json')
                ->post($delivery->webhook->url);
        } catch (ConnectionException $exception) {
            $this->retryOrFail($delivery, $exception->getMessage());

            return;
        } catch (Throwable $exception) {
            $this->markFailed($delivery, $exception->getMessage());

            return;
        }

        if ($response->successful()) {
            $delivery->forceFill([
                'status' => 'delivered',
                'response_status' => $response->status(),
                'error' => null,
                'delivered_at' => now(),
            ])->save();

            return;
        }

        $message = 'O destino respondeu com o status '.$response->status().'.';
        if ($this->isTransient($response->status())) {
            $this->retryOrFail($delivery, $message, $response->status());

            return;
        }

        $this->markFailed($delivery, $message, $response->status());
    }

    public function failed(?Throwable $exception): void
    {
        $delivery = FlowEventDelivery::withoutGlobalScopes()->find($this->deliveryId);
        if ($delivery === null || $delivery->status === 'delivered') {
            return;
        }

        $this->markFailed(
            $delivery,
            $exception?->getMessage() ?? 'A entrega foi tentada novamente e esgotou as tentativas.',
            $exception instanceof FlowEventWebhookDeliveryException ? $exception->responseStatus : null,
        );
    }

    private function retryOrFail(FlowEventDelivery $delivery, string $message, ?int $status = null): void
    {
        $delivery->forceFill([
            'response_status' => $status,
            'error' => $message,
            'next_attempt_at' => $delivery->attempts < $this->tries ? now()->addSeconds($this->backoff()[$delivery->attempts - 1] ?? 30) : null,
        ])->save();

        if ($delivery->attempts >= $this->tries) {
            $this->markFailed($delivery, $message, $status);

            return;
        }

        throw new FlowEventWebhookDeliveryException($message, $status);
    }

    private function markFailed(FlowEventDelivery $delivery, string $message, ?int $status = null): void
    {
        $delivery->forceFill([
            'status' => 'failed',
            'response_status' => $status ?? $delivery->response_status,
            'error' => $message,
            'next_attempt_at' => null,
        ])->save();
    }

    private function isTransient(int $status): bool
    {
        return $status === 408 || $status === 425 || $status === 429 || $status >= 500;
    }
}
