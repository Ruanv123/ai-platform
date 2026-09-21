<?php

namespace App\Flows;

use App\Ai\Agents\WhatsAppAgent;
use App\Ai\Tools\ReturnToFlow;
use App\Enums\FlowCategory;
use App\Enums\FlowCollisionPolicy;
use App\Enums\FlowExecutionStatus;
use App\Enums\MessageDeliveryStatus;
use App\Enums\MessageDirection;
use App\Jobs\WakeFlowExecution;
use App\Knowledge\AgentInstructionTemplate;
use App\Knowledge\KnowledgeBaseSearch;
use App\Models\Agent;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\ContactField;
use App\Models\ContactFieldValue;
use App\Models\Flow;
use App\Models\FlowExecution;
use App\Models\FlowVersion;
use App\Models\GlobalVariable;
use App\Models\Message;
use App\Models\Tag;
use App\WhatsApp\Output\WhatsAppOutputAdapterFactory;
use App\WhatsApp\WhatsAppCustomerCareWindow;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

final class FlowEngine
{
    public function __construct(
        private readonly FlowDslValidator $validator,
        private readonly ExpressionEvaluator $expressions,
        private readonly WhatsAppOutputAdapterFactory $outputAdapterFactory,
        private readonly WhatsAppCustomerCareWindow $customerCareWindow,
        private readonly AgentInstructionTemplate $instructionTemplate,
        private readonly KnowledgeBaseSearch $knowledgeBaseSearch,
    ) {}

    public function hasActiveMessageFlows(Channel $channel): bool
    {
        return $this->messageFlows($channel)->where('status', 'published')->exists();
    }

    public function hasManagedMessageFlows(Channel $channel): bool
    {
        return $this->messageFlows($channel)->whereIn('status', ['published', 'paused'])->exists();
    }

    /** @return Builder<Flow> */
    private function messageFlows(Channel $channel): Builder
    {
        return Flow::withoutGlobalScopes()
            ->where('team_id', $channel->team_id)
            ->where('channel_id', $channel->getKey())
            ->where('trigger_type', 'message');
    }

    public function handleMessage(Channel $channel, Message $message): void
    {
        $contact = $message->contact()->withoutGlobalScopes()->first();
        if ($contact === null || $contact->human_waiting) {
            return;
        }

        $active = $this->activeConversation($channel, $contact);
        if ($active !== null) {
            $flow = Flow::withoutGlobalScopes()->find($active->flowVersion->flow_id);
            if ($flow !== null) {
                $this->applyCollisionPolicy($active, $flow, $message);
                $message->forceFill(['is_responded' => true])->save();
            }
        }

        $flows = Flow::withoutGlobalScopes()
            ->where('team_id', $channel->team_id)
            ->where('channel_id', $channel->getKey())
            ->where('trigger_type', 'message')
            ->where('status', 'published')
            ->get();

        foreach ($flows as $flow) {
            $version = FlowVersion::withoutGlobalScopes()
                ->where('flow_id', $flow->getKey())
                ->latest('number')
                ->first();
            if ($version === null) {
                continue;
            }

            if ($version->category === FlowCategory::Conversation) {
                if ($active === null) {
                    $this->start($flow, $version, $channel, $contact, ['message' => $message->content, 'payload_type' => $message->payload_type], $message);
                }

                continue;
            }

            $execution = $this->createExecution(
                $flow,
                $version,
                $channel,
                $contact,
                ['message' => $message->content, 'payload_type' => $message->payload_type],
            );
            $this->run($execution, $message, reaction: true);
            $message->forceFill(['is_responded' => true])->save();
        }

        if ($flows->isNotEmpty() || $this->hasManagedMessageFlows($channel)) {
            $message->forceFill(['is_responded' => true])->save();
        }
    }

    /** @param array<string, mixed> $payload */
    public function handleWebhook(Flow $flow, Channel $channel, array $payload, ?Contact $contact): void
    {
        $version = FlowVersion::withoutGlobalScopes()
            ->where('flow_id', $flow->getKey())
            ->latest('number')
            ->first();
        if ($version === null) {
            return;
        }

        $eventId = data_get($payload, 'id') ?? data_get($payload, 'event_id');
        if (is_scalar($eventId) && filled($eventId)) {
            $alreadyProcessed = FlowExecution::withoutGlobalScopes()
                ->where('team_id', $flow->team_id)
                ->where('flow_version_id', $version->getKey())
                ->latest('created_at')
                ->limit(100)
                ->get()
                ->contains(fn (FlowExecution $candidate): bool => (string) data_get($candidate->variables, '__event_id') === (string) $eventId);
            if ($alreadyProcessed) {
                return;
            }
            $payload['__event_id'] = (string) $eventId;
        }

        if ($version->category === FlowCategory::Conversation && $contact !== null) {
            $active = $this->activeConversation($channel, $contact);
            if ($active !== null) {
                $this->applyCollisionPolicy($active, $flow, null, $payload);

                return;
            }
        }

        $execution = $this->createExecution($flow, $version, $channel, $contact, $payload);
        $this->run($execution, null, reaction: $version->category === FlowCategory::Reaction);
    }

    public function wake(FlowExecution $execution): void
    {
        $execution = FlowExecution::withoutGlobalScopes()
            ->whereKey($execution->getKey())
            ->lockForUpdate()
            ->first();
        if ($execution === null || ! in_array($execution->status, [FlowExecutionStatus::Waiting, FlowExecutionStatus::Paused], true)) {
            return;
        }

        if ($execution->expires_at->isPast()) {
            $execution->finish(FlowExecutionStatus::Failed, 'execution_ttl_exceeded');

            return;
        }

        if ($execution->wake_at !== null && $execution->wake_at->isFuture()) {
            return;
        }

        $waitingFor = $execution->waiting_for;
        $execution->forceFill([
            'status' => FlowExecutionStatus::Running,
            'wake_at' => null,
            'waiting_for' => null,
        ])->save();
        if ($waitingFor === 'human' && $execution->contact_id !== null) {
            Contact::withoutGlobalScopes()->whereKey($execution->contact_id)->update([
                'human_waiting' => false,
                'human_waiting_at' => null,
            ]);
        }
        $this->hydrate($execution);
        if ($waitingFor === 'contact_response') {
            $this->runTimeout($execution);

            return;
        }
        if (in_array($waitingFor, ['timer', 'human'], true)) {
            $dsl = $this->validator->normalize($execution->flowVersion->dsl);
            $execution->current_node_id = $this->nextNode($dsl, (string) $execution->current_node_id);
            $execution->save();
        }
        $this->run($execution, null);
    }

    public function reconcile(): int
    {
        $executions = FlowExecution::withoutGlobalScopes()
            ->whereIn('status', [FlowExecutionStatus::Waiting, FlowExecutionStatus::Paused])
            ->whereNotNull('wake_at')
            ->where('wake_at', '<=', now())
            ->limit(500)
            ->get();

        foreach ($executions as $execution) {
            $this->wake($execution);
        }

        return $executions->count();
    }

    private function runTimeout(FlowExecution $execution): void
    {
        $dsl = $this->validator->normalize($execution->flowVersion->dsl);
        $node = collect($dsl['nodes'])->firstWhere('id', $execution->current_node_id);
        if (! is_array($node)) {
            $execution->finish(FlowExecutionStatus::Failed, 'node_not_found');

            return;
        }

        $result = $this->exceptionResult($execution, $node, 'contact_timeout');
        if (($result['finish'] ?? false) === true) {
            $execution->finish(FlowExecutionStatus::Failed, (string) ($result['reason'] ?? 'contact_timeout'));

            return;
        }

        $execution->current_node_id = $result['next'] ?? $this->nextNode($dsl, $node['id']);
        $execution->save();
        $this->run($execution, null);
    }

    public function returnToFlow(FlowExecution $execution): void
    {
        if (! $execution->isActive()) {
            return;
        }

        $execution->forceFill([
            'status' => FlowExecutionStatus::Running,
            'waiting_for' => null,
            'wake_at' => null,
        ])->save();
        if ($execution->contact_id !== null) {
            Contact::withoutGlobalScopes()->whereKey($execution->contact_id)->update([
                'human_waiting' => false,
                'human_waiting_at' => null,
            ]);
        }
        $this->hydrate($execution);
        $dsl = $this->validator->normalize($execution->flowVersion->dsl);
        $execution->current_node_id = $this->nextNode($dsl, (string) $execution->current_node_id);
        $execution->save();
        $this->run($execution, null);
    }

    /** @param array<string, mixed> $variables */
    private function start(
        Flow $flow,
        FlowVersion $version,
        Channel $channel,
        Contact $contact,
        array $variables,
        ?Message $message,
    ): void {
        $execution = $this->createExecution($flow, $version, $channel, $contact, $variables);
        $this->run($execution, $message);
    }

    /** @param array<string, mixed> $variables */
    private function createExecution(
        Flow $flow,
        FlowVersion $version,
        Channel $channel,
        ?Contact $contact,
        array $variables,
    ): FlowExecution {
        $activeCount = FlowExecution::withoutGlobalScopes()
            ->where('team_id', $flow->team_id)
            ->whereIn('status', [FlowExecutionStatus::Running, FlowExecutionStatus::Waiting, FlowExecutionStatus::Paused])
            ->count();

        $root = $this->rootNode($version->dsl);
        $execution = new FlowExecution([
            'team_id' => $flow->team_id,
            'flow_version_id' => $version->getKey(),
            'contact_id' => $contact?->getKey(),
            'channel_id' => $channel->getKey(),
            'current_node_id' => $root['id'],
            'variables' => $variables,
            'trace' => [],
            'emitted_events' => [],
            'queued_triggers' => [],
            'status' => FlowExecutionStatus::Running,
            'expires_at' => CarbonImmutable::now()->addDays((int) config('flows.execution_ttl_days', 30)),
            'last_activity_at' => now(),
        ]);

        if ($activeCount >= (int) config('flows.max_active_executions_per_team', 100)) {
            $execution->status = FlowExecutionStatus::Failed;
            $execution->final_reason = 'concurrent_execution_limit';
        }

        $execution->saveQuietly();

        return $execution;
    }

    private function run(FlowExecution $execution, ?Message $message, bool $reaction = false): void
    {
        if (! $execution->isActive() || $execution->expires_at->isPast()) {
            if ($execution->expires_at->isPast() && $execution->isActive()) {
                $execution->finish(FlowExecutionStatus::Failed, 'execution_ttl_exceeded');
            }

            return;
        }

        $this->hydrate($execution);
        $dsl = $this->validator->normalize($execution->flowVersion->dsl);
        $nodes = collect($dsl['nodes'])->keyBy('id')->all();
        $steps = 0;

        while ($execution->current_node_id !== null && $steps < (int) config('flows.max_steps_per_turn', 50)) {
            if ($execution->expires_at->isPast()) {
                $execution->finish(FlowExecutionStatus::Failed, 'execution_ttl_exceeded');

                return;
            }

            $node = $nodes[$execution->current_node_id] ?? null;
            if (! is_array($node)) {
                $execution->finish(FlowExecutionStatus::Failed, 'node_not_found');

                return;
            }

            $steps++;
            $execution->attempts++;
            $this->trace($execution, $node['id'], 'started');

            try {
                $result = $this->executeNode($execution, $node, $message);
            } catch (Throwable $exception) {
                report($exception);
                $this->trace($execution, $node['id'], 'failed', $exception->getMessage());
                $result = $this->exceptionResult($execution, $node, $exception->getMessage());
            }

            $this->trace($execution, $node['id'], ($result['wait'] ?? false) ? 'waiting' : 'completed');
            if (($result['wait'] ?? false) === true) {
                $execution->save();

                return;
            }

            if (($result['finish'] ?? false) === true) {
                $execution->finish(
                    ($result['failed'] ?? false) ? FlowExecutionStatus::Failed : FlowExecutionStatus::Completed,
                    (string) ($result['reason'] ?? 'completed'),
                );
                $this->drainQueued($execution);

                return;
            }

            $next = $result['next'] ?? $this->nextNode($dsl, $node['id']);
            $execution->current_node_id = is_string($next) && $next !== '' ? $next : null;
            $execution->last_activity_at = Carbon::now();
            $execution->save();
            $message = null;
        }

        if ($execution->current_node_id !== null) {
            $execution->finish(FlowExecutionStatus::Failed, 'step_limit_exceeded');
        } else {
            $execution->finish(FlowExecutionStatus::Completed, 'completed');
        }
        $this->drainQueued($execution);
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function executeNode(FlowExecution $execution, array $node, ?Message $message): array
    {
        $type = str_replace('-', '_', strtolower((string) $node['type']));
        $config = is_array($node['config'] ?? null) ? $node['config'] : [];

        return match ($type) {
            'trigger' => [],
            'message' => $this->messageNode($execution, $node, $config),
            'capture_response' => $this->captureNode($execution, $node, $config, $message),
            'condition' => $this->conditionNode($execution, $node, $config),
            'ab_test', 'test_ab' => $this->abNode($execution, $node),
            'wait' => $this->waitNode($execution, $node, $config),
            'define_variable', 'set_variable', 'set_variable_or_tag' => $this->defineNode($execution, $config),
            'deliver_agent' => $this->agentNode($execution, $node, $config, $message),
            'transfer_human' => $this->transferNode($execution, $config),
            'emit_event' => $this->eventNode($execution, $config),
            'http_request' => $this->httpNode($execution, $node, $config),
            'send_template' => $this->templateNode($execution, $config),
            'subflow', 'sub_flow' => $this->subflowNode($execution, $config),
            'ai_task' => $this->aiTaskNode($execution, $node, $config),
            'intent_router', 'route_intent' => $this->intentNode($execution, $node, $config, $message),
            'exception' => [],
            'end', 'finish' => ['finish' => true, 'reason' => 'completed'],
            default => throw new RuntimeException("Tipo de Nó desconhecido: {$type}"),
        };
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function messageNode(FlowExecution $execution, array $node, array $config): array
    {
        $content = $this->interpolate((string) ($config['text'] ?? $config['message'] ?? ''), $execution);
        if ($content === '') {
            throw new RuntimeException('O Nó de Mensagem não possui texto.');
        }

        $this->sendText($execution, $content, allowOutsideWindow: false);

        return [];
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function captureNode(FlowExecution $execution, array $node, array $config, ?Message $message): array
    {
        if ($message === null) {
            $execution->forceFill([
                'status' => FlowExecutionStatus::Waiting,
                'waiting_for' => 'contact_response',
                'wake_at' => $this->timeoutAt($config),
            ])->save();

            return ['wait' => true];
        }

        $value = $message->content;
        if (! $this->validCapture($value, $config)) {
            $message->forceFill(['is_responded' => true])->save();

            return $this->exceptionResult($execution, $node, 'invalid_capture');
        }

        $variable = (string) ($config['variable'] ?? $config['name'] ?? 'response');
        $storedValue = $value;
        if (($config['format'] ?? $config['type'] ?? 'text') === 'number' && is_numeric($value)) {
            $storedValue = str_contains($value, '.') ? (float) $value : (int) $value;
        }
        $variables = $execution->variables;
        data_set($variables, $variable, $storedValue);
        $execution->variables = $variables;
        $message->forceFill(['is_responded' => true])->save();

        return [];
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function conditionNode(FlowExecution $execution, array $node, array $config): array
    {
        $conditions = $config['conditions'] ?? $config['branches'] ?? [];
        if (! is_array($conditions)) {
            throw new RuntimeException('As condições do Nó precisam ser uma lista.');
        }

        foreach ($conditions as $condition) {
            if (! is_array($condition)) {
                continue;
            }

            try {
                if ($this->expressions->evaluate((string) ($condition['expression'] ?? ''), $this->expressionContext($execution))) {
                    return ['next' => $condition['target'] ?? $condition['next'] ?? null];
                }
            } catch (Throwable $exception) {
                $error = $this->edgeFor($execution, $node['id'], 'error');
                if ($error !== null) {
                    return ['next' => $error];
                }

                throw $exception;
            }
        }

        $else = collect($this->edges($execution->flowVersion->dsl, $node['id']))
            ->first(fn (array $edge): bool => in_array(strtolower((string) ($edge['label'] ?? $edge['condition'] ?? '')), ['else', 'otherwise', 'senão', 'no'], true));

        return ['next' => $else['target'] ?? $config['else'] ?? null];
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function abNode(FlowExecution $execution, array $node): array
    {
        $edges = $this->edges($execution->flowVersion->dsl, $node['id']);
        if ($edges === []) {
            return [];
        }

        $seed = crc32($execution->getKey().':'.$node['id']);
        $selected = $edges[$seed % count($edges)];

        return ['next' => $selected['target']];
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function waitNode(FlowExecution $execution, array $node, array $config): array
    {
        $seconds = $config['seconds'] ?? null;
        if ($seconds === null && isset($config['minutes'])) {
            $seconds = (int) $config['minutes'] * 60;
        }
        if ($seconds === null && isset($config['hours'])) {
            $seconds = (int) $config['hours'] * 3600;
        }
        if ($seconds === null && isset($config['days'])) {
            $seconds = (int) $config['days'] * 86400;
        }

        $wakeAt = CarbonImmutable::now()->addSeconds(max(1, (int) ($seconds ?? 0)));
        $execution->forceFill([
            'status' => FlowExecutionStatus::Waiting,
            'waiting_for' => 'timer',
            'wake_at' => $wakeAt,
        ])->save();
        dispatch(new WakeFlowExecution($execution->getKey()))->delay($wakeAt);

        return ['wait' => true];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function defineNode(FlowExecution $execution, array $config): array
    {
        $operation = (string) ($config['operation'] ?? $config['target'] ?? $config['action'] ?? 'variable');
        $name = (string) ($config['name'] ?? $config['variable'] ?? $config['tag'] ?? $config['tag_name'] ?? $config['field'] ?? $config['field_name'] ?? 'value');
        $value = $this->interpolateValue($config['value'] ?? null, $execution);

        if (in_array($operation, ['tag', 'set_tag', 'add_tag', 'remove_tag'], true)) {
            $this->setTag($execution, $name, $operation !== 'remove_tag');

            return [];
        }

        if (in_array($operation, ['field', 'contact_field'], true) || isset($config['field'])) {
            $this->setContactField($execution, (string) ($config['field'] ?? $name), $value);

            return [];
        }

        $variables = $execution->variables;
        data_set($variables, $name, $value);
        $execution->variables = $variables;
        $execution->save();

        return [];
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function agentNode(FlowExecution $execution, array $node, array $config, ?Message $message): array
    {
        $agentId = $config['agent_id'] ?? null;
        $agent = is_string($agentId)
            ? Agent::withoutGlobalScopes()->where('team_id', $execution->team_id)->find($agentId)
            : $execution->channel?->agent;
        if ($agent === null || ! $agent->is_active) {
            return $this->exceptionResult($execution, $node, 'agent_inactive');
        }

        if ($message === null && $execution->waiting_for === null) {
            $message = $execution->contact?->messages()
                ->where('channel_id', $execution->channel_id)
                ->latest('created_at')
                ->first();
        }

        if ($message !== null || $execution->waiting_for === null) {
            if ($message !== null) {
                $variables = $execution->variables;
                $variables['message'] = $message->content;
                $variables['last_message'] = $message->content;
                $execution->variables = $variables;
                $execution->save();
            }
            $history = $execution->contact?->history($execution->channel) ?? new Collection;
            if ($message !== null) {
                $history = $history->reject(fn (Message $historyMessage): bool => $historyMessage->is($message))->values();
            }
            $prompt = $message !== null ? $message->content : $this->interpolate((string) ($config['prompt'] ?? ''), $execution);
            $response = (new WhatsAppAgent(
                $agent,
                $history,
                $this->instructionTemplate,
                $this->knowledgeBaseSearch,
                new ReturnToFlow($execution),
            ))->prompt($prompt, provider: config('ai.playground.provider'), model: config('ai.playground.model'), timeout: (int) config('ai.playground.timeout', 60));
            $text = trim($response->text);
            if ($text !== '') {
                $this->sendText($execution, $text, allowOutsideWindow: false);
            }
            if ($message !== null) {
                $message->forceFill(['is_responded' => true])->save();
            }
        }

        $returnRequested = (bool) data_get($execution->variables, '__return_to_flow', false);
        if ($returnRequested) {
            $variables = $execution->variables;
            unset($variables['__return_to_flow']);
            $execution->variables = $variables;
            $execution->save();
        }

        $returnWhen = $config['return_when'] ?? $config['return_condition'] ?? null;
        if (! $returnRequested && is_string($returnWhen) && $returnWhen !== '') {
            try {
                if (! $this->expressions->evaluate($returnWhen, $this->expressionContext($execution))) {
                    $execution->forceFill(['status' => FlowExecutionStatus::Waiting, 'waiting_for' => 'agent'])->save();

                    return ['wait' => true];
                }
            } catch (Throwable) {
                $execution->forceFill(['status' => FlowExecutionStatus::Waiting, 'waiting_for' => 'agent'])->save();

                return ['wait' => true];
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function transferNode(FlowExecution $execution, array $config): array
    {
        $contact = $execution->contact;
        if ($contact !== null) {
            $contact->forceFill(['human_waiting' => true, 'human_waiting_at' => now()])->save();
        }
        $inactivity = (int) ($config['return_after_seconds'] ?? 86400);
        $execution->forceFill([
            'status' => FlowExecutionStatus::Paused,
            'waiting_for' => 'human',
            'wake_at' => CarbonImmutable::now()->addSeconds($inactivity),
        ])->save();
        dispatch(new WakeFlowExecution($execution->getKey()))->delay($execution->wake_at);

        return ['wait' => true];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function eventNode(FlowExecution $execution, array $config): array
    {
        $event = (string) ($config['name'] ?? $config['event'] ?? 'event');
        $events = $execution->emitted_events;
        $events[] = $event;
        $execution->emitted_events = array_values(array_unique($events));
        $execution->save();

        return [];
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function httpNode(FlowExecution $execution, array $node, array $config): array
    {
        $method = strtoupper((string) ($config['method'] ?? 'GET'));
        $url = $this->interpolate((string) ($config['url'] ?? ''), $execution);
        $headers = $this->interpolateArray($config['headers'] ?? [], $execution);
        $body = $this->interpolateValue($config['body'] ?? [], $execution);
        $request = Http::withHeaders($headers)->retry((int) config('flows.http_retry_times', 2), 100, throw: false)->timeout(15);
        $response = $request->send($method, $url, $method === 'GET' ? ['query' => $body] : ['json' => $body]);

        if ($response->failed()) {
            $error = $this->edgeFor($execution, $node['id'], 'error');
            if ($error !== null) {
                return ['next' => $error];
            }

            throw new RuntimeException('A Requisição HTTP falhou.');
        }

        $variables = $execution->variables;
        foreach ((array) ($config['response_map'] ?? $config['map'] ?? []) as $name => $path) {
            data_set($variables, (string) $name, data_get($response->json(), (string) $path));
        }
        $execution->variables = $variables;
        $execution->save();

        return [];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function templateNode(FlowExecution $execution, array $config): array
    {
        $template = (string) ($config['template'] ?? $config['name'] ?? 'template');
        $parameters = array_map(fn (mixed $value): mixed => $this->interpolateValue($value, $execution), (array) ($config['parameters'] ?? []));
        $channel = $execution->channel;
        $contact = $execution->contact;
        if ($channel === null || $contact === null) {
            throw new RuntimeException('O Nó de Template precisa de Canal e Contato.');
        }
        $this->outputAdapterFactory->make($channel)->sendTemplate(
            $channel,
            $contact,
            $template,
            array_values(array_map('strval', $parameters)),
        );

        return [];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function subflowNode(FlowExecution $execution, array $config): array
    {
        if ($execution->depth >= (int) config('flows.max_subflow_depth', 5)) {
            return ['finish' => true, 'failed' => true, 'reason' => 'subflow_depth_limit'];
        }

        $flowId = $config['flow_id'] ?? null;
        $flow = is_string($flowId)
            ? Flow::withoutGlobalScopes()->where('team_id', $execution->team_id)->find($flowId)
            : null;
        $version = $flow?->publishedVersion();
        if ($flow === null || $version === null) {
            return ['next' => $config['error'] ?? null];
        }

        $inputs = [];
        foreach ((array) ($config['inputs'] ?? []) as $name => $value) {
            $inputs[(string) $name] = $this->interpolateValue($value, $execution);
        }
        $child = $this->createExecution($flow, $version, $execution->channel, $execution->contact, $inputs);
        $child->depth = $execution->depth + 1;
        $child->save();
        $this->run($child, null);
        $returns = $child->variables;
        $variables = $execution->variables;
        foreach ((array) ($config['returns'] ?? []) as $target => $source) {
            data_set($variables, (string) $target, data_get($returns, (string) $source));
        }
        $execution->variables = $variables;
        $execution->save();

        return [];
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function aiTaskNode(FlowExecution $execution, array $node, array $config): array
    {
        $agent = $execution->channel?->agent;
        if ($agent === null || ! $agent->is_active) {
            return ['next' => $config['error'] ?? null];
        }

        try {
            $history = $execution->contact?->history($execution->channel) ?? new Collection;
            $response = (new WhatsAppAgent($agent, $history, $this->instructionTemplate, $this->knowledgeBaseSearch))
                ->prompt($this->interpolate((string) ($config['instruction'] ?? $config['prompt'] ?? ''), $execution), provider: config('ai.playground.provider'), model: config('ai.playground.model'), timeout: (int) config('ai.playground.timeout', 60));
            $value = trim($response->text);
            if (($config['structured'] ?? false) === true) {
                $value = json_decode($value, true, flags: JSON_THROW_ON_ERROR);
            }
            $variables = $execution->variables;
            data_set($variables, (string) ($config['variable'] ?? $config['output'] ?? 'ai_result'), $value);
            $execution->variables = $variables;
            $execution->save();

            return [];
        } catch (Throwable) {
            return ['next' => $config['error'] ?? null];
        }
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function intentNode(FlowExecution $execution, array $node, array $config, ?Message $message): array
    {
        $agent = $execution->channel?->agent;
        if ($agent === null || ! $agent->is_active) {
            return ['next' => $config['none'] ?? $config['error'] ?? null];
        }

        try {
            $history = $execution->contact?->history($execution->channel) ?? new Collection;
            $prompt = $message !== null ? $message->content : (string) ($execution->variables['message'] ?? '');
            $response = (new WhatsAppAgent($agent, $history, $this->instructionTemplate, $this->knowledgeBaseSearch))
                ->prompt($prompt, provider: config('ai.playground.provider'), model: config('ai.playground.model'), timeout: (int) config('ai.playground.timeout', 60));
            $intent = trim($response->text);
            $variables = $execution->variables;
            data_set($variables, (string) ($config['variable'] ?? 'intent'), $intent);
            $execution->variables = $variables;
            $execution->save();

            foreach ((array) ($config['options'] ?? []) as $option) {
                if (is_array($option) && strcasecmp((string) ($option['name'] ?? ''), $intent) === 0) {
                    return ['next' => $option['target'] ?? null];
                }
            }
        } catch (Throwable) {
            return ['next' => $config['error'] ?? $config['none'] ?? null];
        }

        return ['next' => $config['none'] ?? null];
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function exceptionResult(FlowExecution $execution, array $node, string $reason): array
    {
        $config = is_array($node['config'] ?? null) ? $node['config'] : [];
        $steps = $config['exception_ladder'] ?? ['agent'];
        $retryKey = '__retries.'.$node['id'];
        $retries = (int) data_get($execution->variables, $retryKey, 0);
        $first = is_array($steps) ? ($steps[$retries] ?? null) : null;
        data_set($execution->variables, $retryKey, $retries + 1);
        $execution->save();

        if (is_array($first)) {
            $first = $first['action'] ?? $first['type'] ?? null;
        }

        $agentNodeId = null;
        $humanNodeId = null;
        foreach ($execution->flowVersion->dsl['nodes'] as $candidate) {
            if (($candidate['type'] ?? null) === 'deliver_agent' && $agentNodeId === null) {
                $agentNodeId = $candidate['id'];
            }
            if (($candidate['type'] ?? null) === 'transfer_human' && $humanNodeId === null) {
                $humanNodeId = $candidate['id'];
            }
        }

        return match ((string) $first) {
            'retry', 'repeat' => $this->retryException($execution, $config),
            'exception' => ['next' => $config['exception_node'] ?? null],
            'agent', 'deliver_agent' => $agentNodeId === null
                ? ['finish' => true, 'failed' => true, 'reason' => $reason]
                : ['next' => $config['agent_node'] ?? $agentNodeId],
            'human', 'transfer_human' => $humanNodeId === null
                ? ['finish' => true, 'failed' => true, 'reason' => $reason]
                : ['next' => $config['human_node'] ?? $humanNodeId],
            'end', 'finish', 'close' => ['finish' => true, 'failed' => true, 'reason' => $reason],
            default => $agentNodeId === null
                ? ['finish' => true, 'failed' => true, 'reason' => $reason]
                : ['next' => $config['agent_node'] ?? $agentNodeId],
        };
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function retryException(FlowExecution $execution, array $config): array
    {
        $reinforcement = $config['reinforcement'] ?? null;
        if (is_string($reinforcement) && $reinforcement !== '') {
            $this->sendText($execution, $this->interpolate($reinforcement, $execution), allowOutsideWindow: false);
        }

        return ['next' => $execution->current_node_id];
    }

    private function sendText(FlowExecution $execution, string $content, bool $allowOutsideWindow): void
    {
        $channel = $execution->channel;
        $contact = $execution->contact;
        if ($channel === null || $contact === null) {
            throw new RuntimeException('O Fluxo precisa de Canal e Contato para enviar Mensagens.');
        }

        $lastIncoming = $contact->messages()
            ->where('channel_id', $channel->getKey())
            ->where('direction', MessageDirection::Incoming)
            ->latest('created_at')
            ->first();
        if (! $allowOutsideWindow && $lastIncoming !== null && $this->customerCareWindow->hasExpired($channel, $lastIncoming)) {
            Message::create([
                'team_id' => $channel->team_id,
                'contact_id' => $contact->getKey(),
                'channel_id' => $channel->getKey(),
                'direction' => MessageDirection::Outgoing,
                'payload_type' => 'text',
                'provider_message_id' => null,
                'content' => $content,
                'delivery_status' => MessageDeliveryStatus::Failed,
            ]);

            return;
        }

        $this->outputAdapterFactory->make($channel)->sendText($channel, $contact, $content);
    }

    /**
     * @param  array<string, mixed>  $dsl
     * @return array<string, mixed>
     */
    private function rootNode(array $dsl): array
    {
        $incoming = [];
        foreach ($dsl['nodes'] as $node) {
            $incoming[(string) $node['id']] = 0;
        }
        foreach ($dsl['edges'] as $edge) {
            $incoming[(string) $edge['target']]++;
        }
        $rootId = array_key_first(array_filter($incoming, fn (int $count): bool => $count === 0));

        foreach ($dsl['nodes'] as $node) {
            if ($node['id'] === $rootId) {
                return $node;
            }
        }

        return $dsl['nodes'][0];
    }

    /**
     * @param  array<string, mixed>  $dsl
     * @return list<array<string, mixed>>
     */
    private function edges(array $dsl, string $source): array
    {
        $edges = [];
        foreach ($dsl['edges'] as $edge) {
            if ($edge['source'] === $source) {
                $edges[] = $edge;
            }
        }

        return $edges;
    }

    /** @param array<string, mixed> $dsl */
    private function nextNode(array $dsl, string $source): ?string
    {
        return $this->edges($dsl, $source)[0]['target'] ?? null;
    }

    private function edgeFor(FlowExecution $execution, string $source, string $label): ?string
    {
        foreach ($this->edges($execution->flowVersion->dsl, $source) as $edge) {
            if (strtolower((string) ($edge['label'] ?? $edge['condition'] ?? '')) === strtolower($label)) {
                return $edge['target'];
            }
        }

        return null;
    }

    /** @param array<string, mixed> $config */
    private function validCapture(?string $value, array $config): bool
    {
        if ($value === null) {
            return false;
        }
        if (isset($config['regex']) && @preg_match((string) $config['regex'], $value) !== 1) {
            return false;
        }
        if (isset($config['pattern']) && @preg_match((string) $config['pattern'], $value) !== 1) {
            return false;
        }
        if (isset($config['choices']) && ! in_array($value, array_map('strval', (array) $config['choices']), true)) {
            return false;
        }

        return match ((string) ($config['format'] ?? $config['type'] ?? 'text')) {
            'number' => is_numeric($value),
            'date' => strtotime($value) !== false,
            default => true,
        };
    }

    /** @param array<string, mixed> $config */
    private function timeoutAt(array $config): ?CarbonImmutable
    {
        $seconds = $config['timeout_seconds'] ?? null;

        return $seconds === null ? null : CarbonImmutable::now()->addSeconds((int) $seconds);
    }

    /** @return array<string, mixed> */
    private function expressionContext(FlowExecution $execution): array
    {
        $contact = $execution->contact;
        $fields = [];
        if ($contact !== null) {
            foreach ($contact->fieldValues()->with('field')->get() as $value) {
                /** @var ContactFieldValue $value */
                $fields[$value->field->name] = $value->value;
            }
        }
        $tags = $contact?->tags()->pluck('name')->mapWithKeys(fn (string $name): array => [$name => true])->all() ?? [];

        $globals = GlobalVariable::withoutGlobalScopes()
            ->where('team_id', $execution->team_id)
            ->pluck('value', 'name')
            ->all();

        return [
            'variables' => $execution->variables,
            'fields' => $fields,
            'tags' => $tags,
            'global' => $globals,
            'payload' => $execution->variables['payload'] ?? $execution->variables,
        ];
    }

    private function interpolate(string $text, FlowExecution $execution): string
    {
        return preg_replace_callback('/{{\s*([^}]+)\s*}}/', function (array $match) use ($execution): string {
            $value = $this->resolveValue(trim($match[1]), $execution);

            return is_scalar($value) ? (string) $value : '';
        }, $text) ?? $text;
    }

    /** @return array<string, mixed> */
    private function interpolateArray(mixed $value, FlowExecution $execution): array
    {
        return is_array($value)
            ? array_map(fn (mixed $item): mixed => is_string($item) ? $this->interpolate($item, $execution) : $this->interpolateValue($item, $execution), $value)
            : [];
    }

    private function interpolateValue(mixed $value, FlowExecution $execution): mixed
    {
        if (is_array($value)) {
            return array_map(fn (mixed $item): mixed => $this->interpolateValue($item, $execution), $value);
        }
        if (! is_string($value)) {
            return $value;
        }

        return preg_match('/^{{\s*([^}]+)\s*}}$/', $value, $match) === 1
            ? $this->resolveValue(trim($match[1]), $execution)
            : $this->interpolate($value, $execution);
    }

    private function resolveValue(string $path, FlowExecution $execution): mixed
    {
        if (str_starts_with($path, 'variables.')) {
            return data_get($execution->variables, substr($path, 10));
        }
        if (str_starts_with($path, 'payload.')) {
            $payload = $execution->variables['payload'] ?? $execution->variables;

            return data_get($payload, substr($path, 8));
        }
        if (str_starts_with($path, 'global.') || str_starts_with($path, 'globals.')) {
            $prefixLength = str_starts_with($path, 'global.') ? 7 : 8;

            return GlobalVariable::withoutGlobalScopes()
                ->where('team_id', $execution->team_id)
                ->where('name', substr($path, $prefixLength))
                ->value('value');
        }
        if (str_starts_with($path, 'field.') || str_starts_with($path, 'contact.') || str_starts_with($path, 'contact_field.')) {
            $name = substr($path, strpos($path, '.') + 1);
            $field = ContactField::withoutGlobalScopes()
                ->where('team_id', $execution->team_id)
                ->where('name', $name)
                ->first();

            return $field === null || $execution->contact === null
                ? null
                : $execution->contact->fieldValues()->where('contact_field_id', $field->getKey())->value('value');
        }
        if (str_starts_with($path, 'tag.')) {
            return $execution->contact?->tags()->where('name', substr($path, 4))->exists();
        }

        return data_get($execution->variables, $path);
    }

    private function setContactField(FlowExecution $execution, string $name, mixed $value): void
    {
        if ($execution->contact === null) {
            return;
        }
        $field = ContactField::withoutGlobalScopes()
            ->where('team_id', $execution->team_id)
            ->where('name', $name)
            ->first();
        if ($field === null) {
            throw new RuntimeException("Campo do Contato {$name} não foi declarado.");
        }
        if ($field->type === 'number' && ! is_numeric($value)) {
            throw new RuntimeException("O Campo {$name} precisa ser numérico.");
        }
        if ($field->type === 'date' && (! is_string($value) || strtotime($value) === false)) {
            throw new RuntimeException("O Campo {$name} precisa ser uma data.");
        }
        if ($field->type === 'choice' && ! in_array((string) $value, array_map('strval', $field->options ?? []), true)) {
            throw new RuntimeException("O valor não é uma opção válida para o Campo {$name}.");
        }

        $execution->contact->fieldValues()->updateOrCreate(
            ['contact_field_id' => $field->getKey()],
            ['value' => is_scalar($value) ? (string) $value : json_encode($value, JSON_THROW_ON_ERROR)],
        );
    }

    private function setTag(FlowExecution $execution, string $name, bool $attach): void
    {
        if ($execution->contact === null) {
            return;
        }
        $tag = Tag::withoutGlobalScopes()->firstOrCreate(['team_id' => $execution->team_id, 'name' => $name]);
        $attach ? $execution->contact->tags()->syncWithoutDetaching([$tag->getKey()]) : $execution->contact->tags()->detach($tag->getKey());
    }

    private function trace(FlowExecution $execution, string $nodeId, string $result, ?string $reason = null): void
    {
        $trace = $execution->trace;
        $trace[] = ['node_id' => $nodeId, 'result' => $result, 'reason' => $reason, 'at' => now()->toIso8601String()];
        $execution->trace = $trace;
        $execution->save();
    }

    private function activeConversation(Channel $channel, Contact $contact): ?FlowExecution
    {
        $execution = FlowExecution::withoutGlobalScopes()
            ->where('team_id', $channel->team_id)
            ->where('channel_id', $channel->getKey())
            ->where('contact_id', $contact->getKey())
            ->whereIn('status', [FlowExecutionStatus::Running, FlowExecutionStatus::Waiting, FlowExecutionStatus::Paused])
            ->latest('created_at')
            ->first();

        if ($execution !== null) {
            $this->hydrate($execution);
        }

        return $execution;
    }

    private function drainQueued(FlowExecution $execution): void
    {
        $queued = $execution->queued_triggers;
        if ($queued === [] || $execution->channel === null) {
            return;
        }

        /** @var array<string, mixed> $payload */
        $payload = array_shift($queued);
        $execution->forceFill(['queued_triggers' => $queued])->save();
        $flow = Flow::withoutGlobalScopes()->find($execution->flowVersion->flow_id);
        if ($flow === null) {
            return;
        }

        $next = $this->createExecution($flow, $execution->flowVersion, $execution->channel, $execution->contact, $payload);
        $this->run($next, null);
    }

    private function hydrate(FlowExecution $execution): void
    {
        $version = FlowVersion::withoutGlobalScopes()->find($execution->flow_version_id);
        if ($version !== null) {
            $version->setRelation('flow', Flow::withoutGlobalScopes()->find($version->flow_id));
        }
        $execution->setRelation('flowVersion', $version);
        $execution->setRelation('channel', Channel::withoutGlobalScopes()->find($execution->channel_id));
        $execution->setRelation('contact', $execution->contact_id === null
            ? null
            : Contact::withoutGlobalScopes()->find($execution->contact_id));
    }

    /** @param array<string, mixed>|null $payload */
    private function applyCollisionPolicy(FlowExecution $execution, Flow $flow, ?Message $message = null, ?array $payload = null): void
    {
        if ($execution->status === FlowExecutionStatus::Paused && $execution->waiting_for === 'human') {
            return;
        }

        $policy = $flow->collision_policy;
        if ($policy === FlowCollisionPolicy::Replace) {
            $execution->finish(FlowExecutionStatus::Completed, 'replaced_by_new_trigger');
            $this->handleWebhook($flow, $execution->channel, $payload ?? ['message' => $message?->content], $execution->contact);

            return;
        }
        if ($policy === FlowCollisionPolicy::Queue) {
            $queued = $execution->queued_triggers;
            $queued[] = $payload ?? ['message' => $message?->content];
            $execution->queued_triggers = $queued;
            $execution->save();

            return;
        }

        if ($message !== null && in_array($execution->waiting_for, ['contact_response', 'agent', null], true)) {
            $this->run($execution, $message);
        }
    }
}
