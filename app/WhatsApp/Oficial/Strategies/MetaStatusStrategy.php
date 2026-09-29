<?php

namespace App\WhatsApp\Oficial\Strategies;

use App\Consent\ContactConsentService;
use App\Enums\ConsentPurpose;
use App\Enums\MessageDeliveryStatus;
use App\Enums\MessageDirection;
use App\Models\Channel;
use App\Models\Message;
use Illuminate\Support\Facades\DB;

final class MetaStatusStrategy implements MetaStrategy
{
    public function shouldDispatchResponse(): bool
    {
        return false;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(Channel $channel, array $payload): ?Message
    {
        $statusData = data_get($payload, 'entry.0.changes.0.value.statuses.0');

        if (! is_array($statusData)) {
            return null;
        }

        $providerMessageId = $statusData['id'] ?? null;
        $status = $statusData['status'] ?? null;
        $deliveryStatus = $this->deliveryStatus($status);

        if (! is_scalar($providerMessageId) || blank($providerMessageId) || $deliveryStatus === null) {
            return null;
        }

        $failureCode = $deliveryStatus === MessageDeliveryStatus::Failed
            ? $this->failureCode($statusData)
            : null;

        DB::transaction(function () use ($channel, $deliveryStatus, $failureCode, $providerMessageId): void {
            $message = Message::query()
                ->where('channel_id', $channel->getKey())
                ->where('direction', MessageDirection::Outgoing)
                ->where('provider_message_id', (string) $providerMessageId)
                ->lockForUpdate()
                ->first();

            if ($message === null) {
                return;
            }

            if ($failureCode === 131050 && app(ContactConsentService::class)->isGranted($message->contact, ConsentPurpose::Marketing)) {
                app(ContactConsentService::class)->revoke(
                    $message->contact,
                    ConsentPurpose::Marketing,
                    'meta_webhook',
                    ['failure_code' => $failureCode],
                );
            }

            if ($deliveryStatus === MessageDeliveryStatus::Failed) {
                if ($message->delivery_status !== MessageDeliveryStatus::Read) {
                    $message->forceFill([
                        'delivery_status' => MessageDeliveryStatus::Failed,
                        'failure_code' => $failureCode,
                        'failure_reason' => $this->failureReason($failureCode),
                    ])->save();
                }

                return;
            }

            if ($message->delivery_status === MessageDeliveryStatus::Failed || $message->delivery_status === MessageDeliveryStatus::Read) {
                return;
            }

            if ($this->rank($deliveryStatus) <= $this->rank($message->delivery_status)) {
                return;
            }

            $message->forceFill([
                'delivery_status' => $deliveryStatus,
                'failure_code' => null,
                'failure_reason' => null,
            ])->save();
        });

        return null;
    }

    private function deliveryStatus(mixed $status): ?MessageDeliveryStatus
    {
        if (! is_string($status)) {
            return null;
        }

        return MessageDeliveryStatus::tryFrom($status);
    }

    private function failureCode(array $statusData): ?int
    {
        $code = data_get($statusData, 'errors.0.code');

        if (is_int($code) && $code >= 0) {
            return $code;
        }

        if (is_string($code) && ctype_digit($code)) {
            return (int) $code;
        }

        return null;
    }

    private function failureReason(?int $failureCode): string
    {
        return match ($failureCode) {
            131047 => 'Janela de atendimento de 24 horas encerrada.',
            131026 => 'Este número não pode receber mensagens pelo WhatsApp.',
            131053 => 'A Meta recusou a mídia enviada.',
            131050 => 'Contato bloqueou mensagens de marketing.',
            default => 'Não foi possível entregar esta mensagem pelo WhatsApp.',
        };
    }

    private function rank(?MessageDeliveryStatus $status): int
    {
        return match ($status) {
            MessageDeliveryStatus::Pending => 0,
            MessageDeliveryStatus::Sent => 1,
            MessageDeliveryStatus::Delivered => 2,
            MessageDeliveryStatus::Read => 3,
            default => -1,
        };
    }
}
