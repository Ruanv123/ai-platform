<?php

namespace App\Http\Controllers\Integrations;

use App\Enums\CrmOperationStatus;
use App\Enums\TeamPermission;
use App\Flows\TraceSanitizer;
use App\Http\Controllers\Controller;
use App\Jobs\Integrations\ProcessCrmOperation;
use App\Models\ContactField;
use App\Models\CrmOperation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CrmOperationController extends Controller
{
    public function index(Request $request, TraceSanitizer $sanitizer): Response
    {
        $team = $request->user()->company();
        abort_if($team === null, 404);
        abort_unless($request->user()->hasTeamPermission($team, TeamPermission::ManageIntegrations), 403);

        $integration = $team->integrations()->where('type', 'crm')->firstOrFail();
        $onlyFailed = $request->boolean('failed');
        $sensitiveFieldKeys = $team->contactFields()
            ->where('is_sensitive', true)
            ->get(['id', 'name'])
            ->flatMap(fn (ContactField $field): array => [(string) $field->getKey(), $field->name])
            ->all();

        $operations = CrmOperation::withoutGlobalScopes()
            ->where('team_id', $team->getKey())
            ->where('integration_id', $integration->getKey())
            ->when($onlyFailed, fn (Builder $query): Builder => $query->where('status', CrmOperationStatus::Failed->value))
            ->with(['contact.fieldValues.field'])
            ->latest('created_at')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('integrations/crm-operations', [
            'integration' => [
                'id' => $integration->getKey(),
                'name' => $integration->name,
                'status' => $integration->status,
            ],
            'operations' => [
                'data' => $operations->getCollection()
                    ->map(fn (CrmOperation $operation): array => $this->serialize($operation, $sensitiveFieldKeys, $sanitizer))
                    ->values()
                    ->all(),
                'currentPage' => $operations->currentPage(),
                'lastPage' => $operations->lastPage(),
                'total' => $operations->total(),
            ],
            'onlyFailed' => $onlyFailed,
            'crmSuccess' => session('crm_success'),
        ]);
    }

    public function retry(Request $request, CrmOperation $operation): RedirectResponse
    {
        $team = $request->user()->company();
        abort_if($team === null, 404);
        abort_unless($request->user()->hasTeamPermission($team, TeamPermission::ManageIntegrations), 403);
        abort_unless($operation->team_id === $team->getKey(), 404);
        abort_unless($operation->status === CrmOperationStatus::Failed, 409);

        $retry = CrmOperation::query()->create([
            'team_id' => $operation->team_id,
            'integration_id' => $operation->integration_id,
            'contact_id' => $operation->contact_id,
            'agent_id' => $operation->agent_id,
            'origin' => 'resend',
            'type' => $operation->type,
            'status' => CrmOperationStatus::Pending,
            'attempts' => 0,
            'token_refresh_attempts' => 0,
            'payload' => $operation->payload,
            'summary' => $operation->summary,
            'expires_at' => now()->addDays(30),
        ]);

        ProcessCrmOperation::dispatch($retry->getKey())
            ->onQueue('crm-operations')
            ->afterCommit();

        return to_route('integrations.crm.operations.index')
            ->with('crm_success', 'Uma nova tentativa foi registrada.');
    }

    private function serialize(CrmOperation $operation, array $sensitiveFieldKeys, TraceSanitizer $sanitizer): array
    {
        $sensitiveFieldContext = $this->sensitiveFieldContext($operation);

        return [
            'id' => $operation->getKey(),
            'origin' => $operation->origin,
            'type' => $operation->type,
            'status' => $operation->status->value,
            'attempts' => $operation->attempts,
            'failureMessage' => $operation->failure_message === null
                ? null
                : $sanitizer->sanitizeText($operation->failure_message, $sensitiveFieldKeys, $sensitiveFieldContext),
            'summary' => $this->sanitizeSummary(
                $sanitizer->sanitize($operation->summary, $sensitiveFieldKeys),
                $sensitiveFieldKeys,
                $sensitiveFieldContext,
                $sanitizer,
            ),
            'contactName' => $operation->contact?->name ?? 'Contato removido',
            'createdAt' => $operation->created_at?->toIso8601String(),
        ];
    }

    private function sensitiveFieldContext(CrmOperation $operation): array
    {
        $sensitiveValues = [];

        foreach ($operation->contact?->fieldValues ?? [] as $fieldValue) {
            $field = $fieldValue->field;
            if ($field?->is_sensitive !== true || ! is_string($fieldValue->value) || $fieldValue->value === '') {
                continue;
            }

            $sensitiveValues[(string) $field->getKey()] = $fieldValue->value;
            $sensitiveValues[$field->name] = $fieldValue->value;
        }

        return ['contact_fields' => $sensitiveValues];
    }

    private function sanitizeSummary(
        mixed $value,
        array $sensitiveFieldKeys,
        array $sensitiveFieldContext,
        TraceSanitizer $sanitizer,
    ): mixed {
        if (is_array($value)) {
            $sanitized = [];
            foreach ($value as $key => $item) {
                $sanitized[$key] = $this->sanitizeSummary($item, $sensitiveFieldKeys, $sensitiveFieldContext, $sanitizer);
            }

            return $sanitized;
        }

        if (is_string($value)) {
            return $sanitizer->sanitizeText($value, $sensitiveFieldKeys, $sensitiveFieldContext);
        }

        if (is_int($value) || is_float($value)) {
            $text = (string) $value;
            $sanitized = $sanitizer->sanitizeText($text, $sensitiveFieldKeys, $sensitiveFieldContext);

            return $sanitized === $text ? $value : '***';
        }

        return $value;
    }
}
