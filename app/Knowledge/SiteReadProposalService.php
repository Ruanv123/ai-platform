<?php

namespace App\Knowledge;

use App\Enums\SiteReadProposalStatus;
use App\Enums\SiteReadStatus;
use App\Enums\TeamSegment;
use App\Enums\Weekday;
use App\Models\SiteRead;
use App\Models\SiteReadProposal;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SiteReadProposalService
{
    private const FIELD_LABELS = [
        'segment' => 'Segmento',
        'business_description' => 'Descrição do negócio',
        'products_services' => 'Produtos e serviços',
        'target_audience' => 'Público-alvo',
        'phone' => 'Telefone público',
        'public_email' => 'E-mail público',
        'address' => 'Endereço',
        'business_hours' => 'Horário de atendimento',
        'brand_voice' => 'Voz da marca',
        'brand_voice_examples' => 'Frases de exemplo da Voz da marca',
    ];

    private const STRING_LIMITS = [
        'business_description' => 5000,
        'products_services' => 5000,
        'target_audience' => 5000,
        'phone' => 40,
        'public_email' => 255,
        'address' => 1000,
        'brand_voice' => 1000,
    ];

    public function store(SiteRead $siteRead, array $output, array $sourceUrls): ?SiteReadProposal
    {
        $fields = $this->normalizeFields($output, $sourceUrls);

        return DB::transaction(function () use ($siteRead, $fields): ?SiteReadProposal {
            $lockedSiteRead = SiteRead::withoutGlobalScopes()->lockForUpdate()->findOrFail($siteRead->getKey());

            if ($lockedSiteRead->status !== SiteReadStatus::Completed) {
                return null;
            }

            $existingProposal = SiteReadProposal::withoutGlobalScopes()
                ->where('site_read_id', $lockedSiteRead->getKey())
                ->first();

            if ($existingProposal !== null) {
                return $existingProposal;
            }

            $team = Team::withoutGlobalScopes()->lockForUpdate()->findOrFail($lockedSiteRead->team_id);
            $latestSiteReadId = SiteRead::withoutGlobalScopes()
                ->where('team_id', $team->getKey())
                ->orderByDesc('started_at')
                ->orderByDesc('id')
                ->value('id');

            if ((int) $latestSiteReadId !== (int) $lockedSiteRead->getKey()) {
                return null;
            }

            $this->replacePendingProposals($team->getKey());

            return SiteReadProposal::withoutGlobalScopes()->create([
                'team_id' => $team->getKey(),
                'site_read_id' => $lockedSiteRead->getKey(),
                'proposed_fields' => $fields,
                'status' => SiteReadProposalStatus::Pending,
            ]);
        });
    }

    public function replacePendingProposals(int $teamId): void
    {
        SiteReadProposal::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->where('status', SiteReadProposalStatus::Pending->value)
            ->lockForUpdate()
            ->get()
            ->each(fn (SiteReadProposal $proposal) => $proposal->update([
                'status' => SiteReadProposalStatus::Replaced,
            ]));
    }

    public function differences(SiteReadProposal $proposal, Team $team): array
    {
        $differences = [];
        $proposedFields = $proposal->proposed_fields ?? [];

        foreach (self::FIELD_LABELS as $field => $label) {
            $proposed = $proposedFields[$field] ?? null;

            if (! is_array($proposed) || ! is_string($proposed['source_url'] ?? null)) {
                continue;
            }

            $currentValue = $team->getAttribute($field);
            $proposedValue = $proposed['value'] ?? null;

            if ($this->normalizeForComparison($currentValue) === $this->normalizeForComparison($proposedValue)) {
                continue;
            }

            $differences[$field] = [
                'label' => $label,
                'current' => $currentValue,
                'proposed' => $proposedValue,
                'sourceUrl' => $proposed['source_url'],
            ];
        }

        return $differences;
    }

    public function acceptFields(SiteReadProposal $proposal, Team $team, array $fieldNames): void
    {
        DB::transaction(function () use ($proposal, $team, $fieldNames): void {
            $lockedProposal = SiteReadProposal::withoutGlobalScopes()->lockForUpdate()->findOrFail($proposal->getKey());
            $lockedTeam = Team::withoutGlobalScopes()->lockForUpdate()->findOrFail($team->getKey());

            if ($lockedProposal->status !== SiteReadProposalStatus::Pending) {
                abort(404);
            }

            $differences = $this->differences($lockedProposal, $lockedTeam);
            $updates = [];

            foreach ($fieldNames as $fieldName) {
                if (array_key_exists($fieldName, $differences)) {
                    $updates[$fieldName] = $differences[$fieldName]['proposed'];
                }
            }

            if ($updates !== []) {
                $lockedTeam->update($updates);
            }

            if ($this->differences($lockedProposal, $lockedTeam) === []) {
                $lockedProposal->update(['status' => SiteReadProposalStatus::Confirmed]);
            }
        });
    }

    public function discard(SiteReadProposal $proposal): void
    {
        SiteReadProposal::withoutGlobalScopes()
            ->whereKey($proposal->getKey())
            ->where('status', SiteReadProposalStatus::Pending->value)
            ->update(['status' => SiteReadProposalStatus::Discarded->value]);
    }

    private function normalizeFields(array $output, array $sourceUrls): array
    {
        $sourceUrls = array_values(array_unique($sourceUrls));
        $fields = [];
        $proposedFields = $output['fields'] ?? [];

        if (! is_array($proposedFields)) {
            return [];
        }

        foreach (array_keys(self::FIELD_LABELS) as $field) {
            $candidate = $proposedFields[$field] ?? null;

            if (! is_array($candidate) || ! is_string($candidate['source_url'] ?? null)) {
                continue;
            }

            $sourceUrl = $candidate['source_url'];
            $value = $candidate['value'] ?? null;

            if (! in_array($sourceUrl, $sourceUrls, true) || $value === null) {
                continue;
            }

            $normalizedValue = $this->normalizeValue($field, $value);

            if ($normalizedValue === null) {
                continue;
            }

            $fields[$field] = [
                'value' => $normalizedValue,
                'source_url' => $sourceUrl,
            ];
        }

        if (! isset($fields['brand_voice'], $fields['brand_voice_examples'])) {
            unset($fields['brand_voice'], $fields['brand_voice_examples']);
        }

        return $fields;
    }

    private function normalizeValue(string $field, mixed $value): mixed
    {
        if ($field === 'segment') {
            return is_string($value) && TeamSegment::tryFrom($value) !== null ? $value : null;
        }

        if ($field === 'business_hours') {
            return $this->normalizeBusinessHours($value);
        }

        if ($field === 'brand_voice_examples') {
            return $this->normalizeExamples($value);
        }

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '' || Str::length($value) > (self::STRING_LIMITS[$field] ?? 0)) {
            return null;
        }

        if ($field === 'public_email' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        return $value;
    }

    private function normalizeBusinessHours(mixed $value): ?array
    {
        if (! is_array($value) || $value === []) {
            return null;
        }

        $hours = [];

        foreach ($value as $dayHours) {
            if (! is_array($dayHours) || ! is_string($dayHours['day'] ?? null)) {
                return null;
            }

            $weekday = Weekday::tryFrom($dayHours['day']);
            $enabled = $dayHours['enabled'] ?? null;
            $open = $dayHours['open'] ?? null;
            $close = $dayHours['close'] ?? null;

            if ($weekday === null || ! is_bool($enabled) || array_key_exists($weekday->value, $hours)) {
                return null;
            }

            if ($enabled && (! $this->isTime($open) || ! $this->isTime($close) || $open >= $close)) {
                return null;
            }

            $hours[$weekday->value] = [
                'enabled' => $enabled,
                'open' => $enabled ? $open : null,
                'close' => $enabled ? $close : null,
            ];
        }

        return $hours;
    }

    private function normalizeExamples(mixed $value): ?array
    {
        if (! is_array($value) || count($value) < 2 || count($value) > 4) {
            return null;
        }

        $examples = [];

        foreach ($value as $example) {
            if (! is_string($example)) {
                return null;
            }

            $example = trim($example);

            if ($example === '' || Str::length($example) > 1000) {
                return null;
            }

            $examples[] = $example;
        }

        return $examples;
    }

    private function isTime(mixed $value): bool
    {
        return is_string($value) && preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value) === 1;
    }

    private function normalizeForComparison(mixed $value): mixed
    {
        if (! is_array($value)) {
            return is_string($value) ? trim($value) : $value;
        }

        $normalized = array_map(fn (mixed $item): mixed => $this->normalizeForComparison($item), $value);

        if (! array_is_list($normalized)) {
            ksort($normalized);
        }

        return $normalized;
    }
}
