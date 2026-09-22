<?php

namespace App\Flows;

use App\Enums\FlowCategory;
use App\Enums\FlowStatus;
use App\Enums\FlowTriggerType;
use App\Models\Channel;
use App\Models\Flow;
use App\Models\FlowEvent;
use App\Models\FlowVersion;
use App\Models\Integration;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

final class FlowPublisher
{
    public function __construct(private readonly FlowDslValidator $validator) {}

    /**
     * @return array{version: FlowVersion, warnings: list<string>}
     */
    public function publish(Flow $flow): array
    {
        $dsl = $this->validate($flow);
        $referenceWarnings = $this->validateExternalReferences($flow, $dsl);
        $this->assertNoSubflowCycle($flow, $dsl);
        $category = $this->validator->category($dsl);
        $warnings = $referenceWarnings;
        $previousCategory = $this->publishedCategory($flow);
        if ($previousCategory !== null && $previousCategory !== $category) {
            $categoryLabel = $category === FlowCategory::Conversation ? 'conversa' : 'reação';
            $warnings[] = 'O Fluxo mudou de natureza: agora é de '.$categoryLabel.'.';
        }

        if ($flow->trigger_type === FlowTriggerType::Message && $flow->channel_id === null) {
            throw new FlowValidationException(['Um gatilho de mensagem precisa escolher um Canal.']);
        }

        if ($flow->trigger_type === FlowTriggerType::Webhook
            && blank($flow->trigger_event)
        ) {
            throw new FlowValidationException(['Um gatilho de webhook precisa declarar o nome do evento.']);
        }

        if ($flow->trigger_type === FlowTriggerType::Webhook) {
            $firstNode = $this->firstExecutableNode($dsl);
            if (($firstNode['type'] ?? null) === 'message') {
                $warnings[] = 'Este webhook começa por uma Mensagem. Um Template aprovado é mais seguro quando a janela de 24 horas está fechada.';
            }
        }

        if ($category === FlowCategory::Conversation) {
            $duplicate = Flow::withoutGlobalScopes()
                ->where('team_id', $flow->team_id)
                ->where('id', '!=', $flow->getKey())
                ->where('channel_id', $flow->channel_id)
                ->where('trigger_type', FlowTriggerType::Message)
                ->where('status', FlowStatus::Published)
                ->get()
                ->contains(fn (Flow $candidate): bool => $this->publishedCategory($candidate) === FlowCategory::Conversation);

            if ($duplicate) {
                throw new FlowValidationException([
                    'Já existe um Fluxo de conversa publicado para este Canal.',
                ]);
            }
        }

        if ($category === FlowCategory::Reaction
            && Flow::withoutGlobalScopes()
                ->where('team_id', $flow->team_id)
                ->where('id', '!=', $flow->getKey())
                ->where('channel_id', $flow->channel_id)
                ->where('trigger_type', FlowTriggerType::Message)
                ->where('status', FlowStatus::Published)
                ->get()
                ->contains(fn (Flow $candidate): bool => $this->publishedCategory($candidate) === FlowCategory::Conversation)
        ) {
            $warnings[] = 'Este Fluxo de reação pode enviar mensagens enquanto há um Fluxo de conversa no Canal.';
        }

        $version = DB::transaction(function () use ($flow, $dsl, $category): FlowVersion {
            $lockedFlow = $flow->newQuery()->lockForUpdate()->whereKey($flow->getKey())->firstOrFail();
            if ($lockedFlow->channel_id !== null) {
                Channel::withoutGlobalScopes()->whereKey($lockedFlow->channel_id)->lockForUpdate()->first();
            }
            $number = ((int) $lockedFlow->versions()->max('number')) + 1;
            $version = $lockedFlow->versions()->create([
                'team_id' => $lockedFlow->team_id,
                'number' => $number,
                'dsl' => $dsl,
                'category' => $category,
                'published_at' => now(),
            ]);
            $lockedFlow->update(['status' => FlowStatus::Published]);

            return $version;
        });

        return ['version' => $version, 'warnings' => $warnings];
    }

    /** @return array{version: int, nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>} */
    public function validate(Flow $flow): array
    {
        try {
            return $this->validator->normalize($flow->draft_dsl);
        } catch (InvalidArgumentException $exception) {
            throw new FlowValidationException([$exception->getMessage()], previous: $exception);
        } catch (Throwable $exception) {
            throw new FlowValidationException(['O desenho do Fluxo é inválido.'], previous: $exception);
        }
    }

    /**
     * @param  array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>}  $dsl
     * @return array<string, mixed>
     */
    private function firstExecutableNode(array $dsl): array
    {
        $nodes = collect($dsl['nodes'])->keyBy('id');
        $incoming = collect($dsl['edges'])->countBy('target');
        $current = $nodes->first(fn (array $node): bool => ! $incoming->has($node['id'])) ?? [];

        while (($current['type'] ?? null) === 'trigger') {
            $nextEdge = collect($dsl['edges'])->firstWhere('source', $current['id']);
            $nextId = is_array($nextEdge) ? ($nextEdge['target'] ?? null) : null;
            if (! is_string($nextId) || ! is_array($nodes->get($nextId))) {
                break;
            }
            $current = $nodes->get($nextId);
        }

        return $current;
    }

    /** @param array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>} $dsl */
    private function validateExternalReferences(Flow $flow, array $dsl): array
    {
        $warnings = [];
        $requestTypes = [
            'http_request',
            'agenda_availability',
            'calendar_availability',
            'check_availability',
            'agenda_create_event',
            'calendar_create_event',
            'create_calendar_event',
        ];

        foreach ($dsl['nodes'] as $node) {
            $type = str_replace('-', '_', strtolower((string) ($node['type'] ?? '')));
            $config = is_array($node['config'] ?? null) ? $node['config'] : [];
            if (in_array($type, $requestTypes, true)) {
                $integrationId = $config['integration_id'] ?? null;
                if (! is_string($integrationId) || $integrationId === '') {
                    throw new FlowValidationException(["O Nó {$node['id']} precisa escolher uma Integração salva; segredos não podem ficar no DSL."]);
                }
                $integration = Integration::withoutGlobalScopes()
                    ->where('team_id', $flow->team_id)
                    ->find($integrationId);
                if ($integration === null) {
                    throw new FlowValidationException(["A Integração do Nó {$node['id']} não existe nesta Empresa."]);
                }
                if ($type !== 'http_request' && $integration->type !== 'agenda') {
                    throw new FlowValidationException(["O Nó {$node['id']} precisa usar uma Integração de Agenda."]);
                }
                if ($this->containsLegacyCredential($config)) {
                    $warnings[] = "O Nó {$node['id']} ainda contém uma credencial antiga no DSL; ela será ignorada. Edite-o para usar a Integração salva.";
                }
            }
            if ($type === 'emit_event') {
                $eventId = $config['event_id'] ?? null;
                if (! is_string($eventId) || $eventId === '') {
                    throw new FlowValidationException(["O Nó {$node['id']} precisa escolher um Evento declarado."]);
                }
                if (! FlowEvent::withoutGlobalScopes()->where('team_id', $flow->team_id)->whereKey($eventId)->exists()) {
                    throw new FlowValidationException(["O Evento do Nó {$node['id']} não existe nesta Empresa."]);
                }
            }
        }

        return $warnings;
    }

    /** @param array<string, mixed> $config */
    private function containsLegacyCredential(array $config): bool
    {
        foreach (['credential', 'credentials', 'api_key', 'api_secret', 'access_token', 'client_secret', 'password'] as $key) {
            if (array_key_exists($key, $config)) {
                return true;
            }
        }

        return false;
    }

    private function publishedCategory(Flow $flow): ?FlowCategory
    {
        $category = FlowVersion::withoutGlobalScopes()
            ->where('flow_id', $flow->getKey())
            ->latest('number')
            ->value('category');

        return $category === null ? null : FlowCategory::from((string) $category);
    }

    /** @param array<string, mixed> $dsl */
    private function assertNoSubflowCycle(Flow $flow, array $dsl): void
    {
        /** @var array<string, bool> $visited */
        $visited = [];
        $this->visitSubflows($flow, $dsl, [], $visited);
    }

    /**
     * @param  array<string, mixed>  $dsl
     * @param  list<string>  $stack
     * @param  array<string, bool>  $visited
     */
    private function visitSubflows(Flow $flow, array $dsl, array $stack, array &$visited): void
    {
        $flowId = (string) $flow->getKey();
        if (in_array($flowId, $stack, true)) {
            throw new FlowValidationException(['O Fluxo contém um ciclo de Sub-fluxos.']);
        }
        if (isset($visited[$flowId])) {
            return;
        }

        $stack[] = $flowId;
        foreach ($dsl['nodes'] as $node) {
            if (! is_array($node) || ! in_array($node['type'] ?? null, ['subflow', 'sub_flow'], true)) {
                continue;
            }
            $childId = data_get($node, 'config.flow_id');
            if (! is_string($childId) || $childId === '') {
                continue;
            }
            $child = Flow::withoutGlobalScopes()
                ->where('team_id', $flow->team_id)
                ->find($childId);
            if ($child === null) {
                throw new FlowValidationException(["O Sub-fluxo {$childId} não existe nesta Empresa."]);
            }
            $childVersion = FlowVersion::withoutGlobalScopes()
                ->where('flow_id', $child->getKey())
                ->latest('number')
                ->first();
            $childDsl = $child->getKey() === $flow->getKey()
                ? $dsl
                : ($childVersion !== null ? $childVersion->dsl : $child->draft_dsl);
            $this->visitSubflows($child, $childDsl, $stack, $visited);
        }
        $visited[$flowId] = true;
    }
}
