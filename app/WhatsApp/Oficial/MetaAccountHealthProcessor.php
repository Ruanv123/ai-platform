<?php

namespace App\WhatsApp\Oficial;

use App\Enums\ChannelStatus;
use App\Models\Channel;
use App\Models\WhatsAppAccount;

final class MetaAccountHealthProcessor
{
    public function process(Channel $channel, string $field, array $value): void
    {
        $channel->loadMissing('whatsappPhoneNumber.whatsappAccount');
        $account = $channel->whatsappPhoneNumber?->whatsappAccount;

        if ($account === null) {
            return;
        }

        switch ($field) {
            case 'account_update':
                $this->processAccountUpdate($channel, $account, $value);

                return;
            case 'account_review_update':
                $this->processAccountReview($account, $value);

                return;
            case 'phone_number_quality_update':
                $this->processQualityUpdate($channel, $value);

                return;
            case 'phone_number_name_update':
                $this->processNameUpdate($channel, $value);
        }
    }

    private function processAccountUpdate(Channel $channel, WhatsAppAccount $account, array $value): void
    {
        $event = $this->upperValue($value['event'] ?? null) ?? '';
        $banState = $this->upperValue(data_get($value, 'ban_info.waba_ban_state'))
            ?? $this->upperValue($value['ban_state'] ?? null);
        $reviewStatus = $this->upperValue($value['review_status'] ?? ($value['decision'] ?? null));
        $attributes = [];

        if ($banState !== null) {
            $attributes['ban_state'] = $banState;
        } elseif ($event === 'PARTNER_REMOVED') {
            $attributes['ban_state'] = $event;
        }

        if ($reviewStatus !== null) {
            $attributes['review_status'] = $reviewStatus;
        }

        if ($attributes !== []) {
            $account->update($attributes);
        }

        if ($this->banWasLifted($event, $banState)) {
            if ($channel->status === ChannelStatus::Error
                && $channel->last_error === 'A Conta do WhatsApp foi bloqueada ou restringida pela Meta.') {
                $channel->update([
                    'status' => ChannelStatus::Connected,
                    'last_error' => null,
                ]);
            }

            return;
        }

        if ($this->accountIsUnavailable($event, $banState, $value)) {
            if ($banState === null) {
                $account->update([
                    'ban_state' => $event !== '' ? $event : 'RESTRICTED',
                ]);
            }

            $channel->update([
                'status' => ChannelStatus::Error,
                'last_error' => $event === 'PARTNER_REMOVED'
                    ? 'A plataforma foi removida como parceira da Conta do WhatsApp na Meta. Reconecte o Canal.'
                    : 'A Conta do WhatsApp foi bloqueada ou restringida pela Meta.',
            ]);
        }
    }

    private function processAccountReview(WhatsAppAccount $account, array $value): void
    {
        $reviewStatus = $this->upperValue($value['decision'] ?? ($value['review_status'] ?? null));

        if ($reviewStatus !== null) {
            $account->update(['review_status' => $reviewStatus]);
        }
    }

    private function processQualityUpdate(Channel $channel, array $value): void
    {
        $phoneNumber = $channel->whatsappPhoneNumber;

        if ($phoneNumber === null) {
            return;
        }

        $event = $this->upperValue($value['event'] ?? null);
        $qualityRating = $this->upperValue($value['quality_rating'] ?? ($value['new_quality_rating'] ?? null))
            ?? match ($event) {
                'FLAGGED' => 'RED',
                'UNFLAGGED' => 'GREEN',
                default => null,
            };
        $messagingLimitTier = $this->upperValue($value['messaging_limit_tier'] ?? ($value['current_limit'] ?? ($value['new_limit'] ?? null)));
        $attributes = array_filter([
            'quality_rating' => $qualityRating,
            'messaging_limit_tier' => $messagingLimitTier,
        ], fn (?string $attribute): bool => $attribute !== null);

        if ($attributes !== []) {
            $phoneNumber->update($attributes);
        }
    }

    private function processNameUpdate(Channel $channel, array $value): void
    {
        $phoneNumber = $channel->whatsappPhoneNumber;

        if ($phoneNumber === null) {
            return;
        }

        $nameStatus = $this->upperValue($value['name_status'] ?? ($value['new_name_status'] ?? ($value['decision'] ?? null)));
        $verifiedName = $this->stringValue($value['verified_name'] ?? ($value['new_display_name'] ?? ($value['requested_verified_name'] ?? null)));
        $attributes = [];

        if ($nameStatus !== null) {
            $attributes['name_status'] = $nameStatus;
        }

        if ($verifiedName !== null && in_array(strtoupper((string) $nameStatus), ['APPROVED', 'VERIFIED'], true)) {
            $attributes['verified_name'] = $verifiedName;
        }

        if ($attributes !== []) {
            $phoneNumber->update($attributes);
        }
    }

    private function accountIsUnavailable(string $event, ?string $banState, array $value): bool
    {
        if (strtoupper((string) $banState) === 'SCHEDULE_FOR_DISABLE') {
            return false;
        }

        if (in_array($event, [
            'PARTNER_REMOVED',
            'ACCOUNT_OFFBOARDED',
            'ACCOUNT_DELETED',
            'ACCOUNT_VIOLATION',
            'ACCOUNT_RESTRICTION',
            'ACCOUNT_RESTRICTED',
            'RESTRICTION',
        ], true)) {
            return true;
        }

        if ($event === 'DISABLED_UPDATE'
            && ! in_array(strtoupper((string) $banState), ['REINSTATE', 'REINSTATED', 'SCHEDULE_FOR_DISABLE'], true)) {
            return true;
        }

        if (is_array($value['restriction_info'] ?? null) && $value['restriction_info'] !== []) {
            return true;
        }

        return $banState !== null && ! in_array(strtoupper($banState), ['REINSTATE', 'REINSTATED', 'SCHEDULE_FOR_DISABLE'], true);
    }

    private function banWasLifted(string $event, ?string $banState): bool
    {
        return ! in_array($event, ['PARTNER_REMOVED', 'ACCOUNT_OFFBOARDED', 'ACCOUNT_DELETED'], true)
            && in_array(strtoupper((string) $banState), ['REINSTATE', 'REINSTATED'], true);
    }

    private function stringValue(mixed $value): ?string
    {
        if (! is_scalar($value) || blank($value)) {
            return null;
        }

        return (string) $value;
    }

    private function upperValue(mixed $value): ?string
    {
        $string = $this->stringValue($value);

        return $string === null ? null : strtoupper($string);
    }
}
