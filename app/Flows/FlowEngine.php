<?php

namespace App\Flows;

use App\Ai\Agents\WhatsAppAgent;
use App\Ai\Tools\ReturnToFlow;
use App\Consent\ContactConsentService;
use App\Enums\ChannelType;
use App\Enums\ConsentPurpose;
use App\Enums\FlowCategory;
use App\Enums\FlowCollisionPolicy;
use App\Enums\FlowExecutionStatus;
use App\Enums\MessageDeliveryStatus;
use App\Enums\MessageDirection;
use App\Events\HumanConversationUpdated;
use App\Inbox\AgentConversationService;
use App\Integrations\IntegrationClient;
use App\Jobs\WakeFlowExecution;
use App\Knowledge\AgentInstructionTemplate;
use App\Knowledge\KnowledgeBaseSearch;
use App\Models\Agent;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\ContactField;
use App\Models\ContactFieldValue;
use App\Models\Flow;
use App\Models\FlowEvent;
use App\Models\FlowExecution;
use App\Models\FlowExecutionStep;
use App\Models\FlowVersion;
use App\Models\GlobalVariable;
use App\Models\Integration;
use App\Models\Message;
use App\Models\Tag;
use App\Models\WhatsAppTemplate;
use App\WhatsApp\Output\WhatsAppOutputAdapterFactory;
use App\WhatsApp\WhatsAppCustomerCareWindow;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Laravel\Ai\Responses\AgentResponse;
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
        private readonly TraceSanitizer $traceSanitizer,
        private readonly FlowExecutionAggregator $aggregator,
        private readonly IntegrationClient $integrationClient,
        private readonly FlowEventBus $eventBus,
        private readonly ContactConsentService $consents,
        private readonly AgentConversationService $agentConversations,
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
        if ($contact === null || $contact->human_waiting
            || $this->agentConversations->isPaused($channel, (int) $contact->getKey())) {
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
            $waitingFor = $execution->waiting_for;
            $this->finishExecution($execution, FlowExecutionStatus::Failed, 'execution_ttl_exceeded');
            if ($waitingFor === 'human') {
                $this->clearHumanWaiting($execution);
                HumanConversationUpdated::dispatch($execution->team_id, $execution->contact_id, $execution->channel_id, 'expired');
            }

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
            ...($waitingFor === 'human' ? [
                'human_assigned_user_id' => null,
                'human_assigned_at' => null,
                'human_last_activity_at' => null,
            ] : []),
        ])->save();
        if ($waitingFor === 'human') {
            $this->clearHumanWaiting($execution);
            HumanConversationUpdated::dispatch($execution->team_id, $execution->contact_id, $execution->channel_id, 'returned');
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
            $this->finishExecution($execution, FlowExecutionStatus::Failed, 'node_not_found');

            return;
        }

        $result = $this->exceptionResult($execution, $node, 'contact_timeout');
        if (($result['finish'] ?? false) === true) {
            $this->finishExecution($execution, FlowExecutionStatus::Abandoned, (string) ($result['reason'] ?? 'contact_timeout'));

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

        if ($execution->expires_at->isPast()) {
            $this->finishExecution($execution, FlowExecutionStatus::Failed, 'execution_ttl_exceeded');
            $this->clearHumanWaiting($execution);
            HumanConversationUpdated::dispatch($execution->team_id, $execution->contact_id, $execution->channel_id, 'returned');

            return;
        }

        $execution->forceFill([
            'status' => FlowExecutionStatus::Running,
            'waiting_for' => null,
            'wake_at' => null,
            'human_assigned_user_id' => null,
            'human_assigned_at' => null,
            'human_last_activity_at' => null,
        ])->save();
        $this->clearHumanWaiting($execution);
        $this->hydrate($execution);
        $dsl = $this->validator->normalize($execution->flowVersion->dsl);
        $execution->current_node_id = $this->nextNode($dsl, (string) $execution->current_node_id);
        $execution->save();
        $this->run($execution, null);
        HumanConversationUpdated::dispatch($execution->team_id, $execution->contact_id, $execution->channel_id, 'returned');
    }

    private function clearHumanWaiting(FlowExecution $execution): void
    {
        if ($execution->contact_id === null) {
            return;
        }

        Contact::withoutGlobalScopes()->whereKey($execution->contact_id)->update([
            'human_waiting' => false,
            'human_waiting_at' => null,
        ]);
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
        $this->aggregator->recordStarted($execution);
        if (! $execution->isActive()) {
            $this->aggregator->recordFinished($execution, $execution->status);
        }

        return $execution;
    }

    private function run(FlowExecution $execution, ?Message $message, bool $reaction = false): void
    {
        if (! $execution->isActive() || $execution->expires_at->isPast()) {
            if ($execution->expires_at->isPast() && $execution->isActive()) {
                $this->finishExecution($execution, FlowExecutionStatus::Failed, 'execution_ttl_exceeded');
            }

            return;
        }

        $this->hydrate($execution);
        $dsl = $this->validator->normalize($execution->flowVersion->dsl);
        $nodes = collect($dsl['nodes'])->keyBy('id')->all();
        $steps = 0;

        while ($execution->current_node_id !== null && $steps < (int) config('flows.max_steps_per_turn', 50)) {
            if ($execution->expires_at->isPast()) {
                $this->finishExecution($execution, FlowExecutionStatus::Failed, 'execution_ttl_exceeded');

                return;
            }

            $node = $nodes[$execution->current_node_id] ?? null;
            if (! is_array($node)) {
                $this->finishExecution($execution, FlowExecutionStatus::Failed, 'node_not_found');

                return;
            }

            $steps++;
            $execution->attempts++;
            $execution->save();
            $startedAt = CarbonImmutable::now();
            $step = $execution->steps()->create([
                'team_id' => $execution->team_id,
                'sequence' => $execution->attempts,
                'node_id' => $node['id'],
                'result' => 'running',
                'duration_ms' => 0,
                'variables_snapshot' => $this->traceSanitizer->sanitizeVariables($execution->variables, $this->nodeConfig($node)),
                'started_at' => $startedAt,
            ]);
            $startedAtNanoseconds = hrtime(true);
            $error = null;

            try {
                $result = $this->executeNode($execution, $node, $message);
            } catch (Throwable $exception) {
                report($exception);
                $error = $exception->getMessage();
                $result = $this->exceptionResult($execution, $node, $error);
                if ($exception instanceof TraceableFlowException) {
                    $result['trace_data'] = $exception->traceData;
                }
            }

            $reportedError = is_string($result['step_error'] ?? null) ? $result['step_error'] : null;
            $stepError = $error ?? $reportedError ?? (($result['failed'] ?? false) ? (string) ($result['reason'] ?? 'node_failed') : null);
            if ($stepError !== null) {
                $stepError = $this->traceSanitizer->sanitizeText(
                    $stepError,
                    $this->sensitiveFields($this->nodeConfig($node)),
                    $execution->variables,
                );
            }
            if (($result['wait'] ?? false) === true) {
                $this->completeStep(
                    $step,
                    $execution,
                    $stepError === null ? 'waiting' : 'failed',
                    $startedAtNanoseconds,
                    $stepError,
                    traceData: $result['trace_data'] ?? [],
                    nodeConfig: $this->nodeConfig($node),
                );
                $execution->save();

                return;
            }

            if (($result['finish'] ?? false) === true) {
                $this->completeStep(
                    $step,
                    $execution,
                    ($result['failed'] ?? false) || $stepError !== null ? 'failed' : 'completed',
                    $startedAtNanoseconds,
                    $stepError,
                    traceData: $result['trace_data'] ?? [],
                    nodeConfig: $this->nodeConfig($node),
                );
                $this->finishExecution(
                    $execution,
                    ($result['failed'] ?? false) ? FlowExecutionStatus::Failed : FlowExecutionStatus::Completed,
                    (string) ($result['reason'] ?? 'completed'),
                );
                $this->drainQueued($execution);

                return;
            }

            $next = $result['next'] ?? $this->nextNode($dsl, $node['id']);
            $followedEdge = is_string($next) ? $this->edgeBetween($dsl, $node['id'], $next) : null;
            $this->completeStep(
                $step,
                $execution,
                $stepError === null ? 'completed' : 'failed',
                $startedAtNanoseconds,
                $stepError,
                $followedEdge,
                traceData: $result['trace_data'] ?? [],
                nodeConfig: $this->nodeConfig($node),
            );
            $execution->current_node_id = is_string($next) && $next !== '' ? $next : null;
            $execution->last_activity_at = Carbon::now();
            $execution->save();
            $message = null;
        }

        if ($execution->current_node_id !== null) {
            $this->finishExecution($execution, FlowExecutionStatus::Failed, 'step_limit_exceeded');
        } else {
            $this->finishExecution($execution, FlowExecutionStatus::Completed, 'completed');
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
            'send_template' => $this->templateNode($execution, $node, $config),
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

        if (! $this->sendText($execution, $content, allowOutsideWindow: false)) {
            return [
                'finish' => true,
                'failed' => true,
                'reason' => 'customer_care_window_closed',
                'step_error' => 'A janela de atendimento de 24 horas está fechada; use um Template aprovado.',
            ];
        }

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

            return $this->withStepError(
                $this->exceptionResult($execution, $node, 'invalid_capture'),
                'invalid_capture',
            );
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
                    return ['next' => $error, 'step_error' => $exception->getMessage()];
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
            return $this->withStepError(
                $this->exceptionResult($execution, $node, 'agent_inactive'),
                'agent_inactive',
            );
        }

        if ($message === null && $execution->waiting_for === null) {
            $message = $execution->contact?->messages()
                ->where('channel_id', $execution->channel_id)
                ->latest('created_at')
                ->first();
        }

        $traceData = [];
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
                $execution->contact,
            ))->prompt($prompt, provider: config('ai.playground.provider'), model: config('ai.playground.model'), timeout: (int) config('ai.playground.timeout', 60));
            $traceData = $this->aiTraceData($prompt, $response, $config, $execution->variables);
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

                    return ['wait' => true, 'trace_data' => $traceData];
                }
            } catch (Throwable) {
                $execution->forceFill(['status' => FlowExecutionStatus::Waiting, 'waiting_for' => 'agent'])->save();

                return ['wait' => true, 'trace_data' => $traceData];
            }
        }

        return ['trace_data' => $traceData];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function transferNode(FlowExecution $execution, array $config): array
    {
        $contact = $execution->contact;
        $now = now();
        if ($contact !== null) {
            $contact->forceFill(['human_waiting' => true, 'human_waiting_at' => $now])->save();
        }

        $reason = $this->humanTransferReason($execution, $config);
        $inactivity = (int) ($config['return_after_seconds'] ?? config('flows.human_return_after_seconds', 86400));
        $execution->forceFill([
            'status' => FlowExecutionStatus::Paused,
            'waiting_for' => 'human',
            'human_transfer_reason' => $reason,
            'human_assigned_user_id' => null,
            'human_assigned_at' => null,
            'human_last_activity_at' => null,
            'wake_at' => CarbonImmutable::now()->addSeconds($inactivity),
        ])->save();
        dispatch(new WakeFlowExecution($execution->getKey()))->delay($execution->wake_at);
        HumanConversationUpdated::dispatch($execution->team_id, $execution->contact_id, $execution->channel_id, 'transferred');

        return ['wait' => true];
    }

    /** @param array<string, mixed> $config */
    private function humanTransferReason(FlowExecution $execution, array $config): string
    {
        $configuredReason = trim((string) ($config['reason'] ?? $config['message'] ?? ''));
        if ($configuredReason !== '') {
            return $configuredReason;
        }

        $exceptionReason = (string) data_get($execution->variables, '__human_transfer_reason', '');
        if ($exceptionReason !== '') {
            return $exceptionReason === 'unsupported_media' ? $exceptionReason : 'exception_exhausted';
        }

        return data_get($execution->variables, 'payload_type') === 'unsupported'
            ? 'unsupported_media'
            : 'contact_requested';
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    /** @param array<string, mixed> $config */
    private function eventNode(FlowExecution $execution, array $config): array
    {
        $eventId = $config['event_id'] ?? null;
        if (! is_string($eventId) || $eventId === '') {
            $legacyName = (string) ($config['name'] ?? $config['event'] ?? '');
            $eventId = (string) FlowEvent::withoutGlobalScopes()
                ->where('team_id', $execution->team_id)
                ->where('name', $legacyName)
                ->value('id');
        }
        if ($eventId === '') {
            throw new RuntimeException('O Nó de Emitir Evento precisa escolher um Evento declarado.');
        }

        $payload = $this->interpolateValue($config['payload'] ?? [], $execution);
        if (! is_array($payload)) {
            $payload = ['value' => $payload];
        }
        $this->eventBus->emit($execution, $eventId, $payload);

        return [];
    }

    /** @param array<string, mixed> $config */
    private function httpNode(FlowExecution $execution, array $node, array $config): array
    {
        try {
            $integration = $this->integrationFor($execution, $config);
        } catch (Throwable $exception) {
            return $this->integrationError($execution, $node, $exception);
        }

        return $this->executeHttpRequest($execution, $node, $config, $integration);
    }

    /** @return array<string, mixed> */
    private function integrationError(FlowExecution $execution, array $node, Throwable $exception): array
    {
        $error = $this->edgeFor($execution, (string) $node['id'], 'error');
        if ($error !== null) {
            return ['next' => $error, 'step_error' => $exception->getMessage()];
        }

        throw $exception;
    }

    /** @param array<string, mixed> $config */
    private function integrationFor(FlowExecution $execution, array $config): Integration
    {
        $integrationId = $config['integration_id'] ?? null;
        if (! is_string($integrationId) || $integrationId === '') {
            throw new RuntimeException('Este Nó precisa escolher uma Integração salva.');
        }

        $integration = Integration::withoutGlobalScopes()
            ->where('team_id', $execution->team_id)
            ->find($integrationId);
        if ($integration === null) {
            throw new RuntimeException('A Integração escolhida não existe nesta Empresa.');
        }
        if ($integration->status === 'error') {
            throw new RuntimeException('A Integração está com erro: '.($integration->status_message ?: 'teste a conexão novamente.'));
        }

        return $integration;
    }

    /**
     * Execute every external request, including typed connectors, through the same retry and trace path.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function executeHttpRequest(FlowExecution $execution, array $node, array $config, Integration $integration): array
    {
        $method = strtoupper((string) ($config['method'] ?? 'GET'));
        $url = $this->integrationClient->url($integration, $this->interpolate((string) ($config['url'] ?? ''), $execution));
        $headers = $this->interpolateArray($config['headers'] ?? [], $execution);
        $body = $this->interpolateValue($config['body'] ?? [], $execution);
        $sensitiveFields = $this->sensitiveFields($config);
        $sanitizedRequest = [
            'method' => $method,
            'url' => $this->traceSanitizer->sanitizeUrl($url, $sensitiveFields),
            'headers' => $this->traceSanitizer->sanitizeHeaders($headers, $sensitiveFields),
            'body' => $this->traceSanitizer->sanitize($body, $sensitiveFields),
        ];
        $retryLimit = max(0, (int) config('flows.http_retry_times', 2));
        $response = null;
        $lastException = null;
        $retryCount = 0;

        for ($attempt = 0; $attempt <= $retryLimit; $attempt++) {
            try {
                $request = $this->integrationClient->request($integration, $headers);
                $response = $request->send($method, $url, $method === 'GET' ? ['query' => $body] : ['json' => $body]);
                $lastException = null;
                $retryable = $response->serverError() || in_array($response->status(), [408, 425, 429], true);
                if (! $retryable || $attempt === $retryLimit) {
                    break;
                }
                $retryCount++;
                usleep(100_000 * (2 ** $attempt));
            } catch (Throwable $exception) {
                $lastException = $exception;
                if ($attempt === $retryLimit) {
                    break;
                }
                $retryCount++;
                usleep(100_000 * (2 ** $attempt));
            }
        }

        $responseBody = $response?->json();
        $traceData = [
            'http_request' => $sanitizedRequest,
            'http_response' => $response === null ? null : [
                'status' => $response->status(),
                'headers' => $this->traceSanitizer->sanitizeHeaders($response->headers(), $sensitiveFields),
                'body' => $this->traceSanitizer->sanitize($responseBody ?? $response->body(), $sensitiveFields),
            ],
            'http_status' => $response?->status(),
            'retry_count' => $retryCount,
        ];

        $error = $this->edgeFor($execution, $node['id'], 'error');
        if ($lastException !== null) {
            $message = 'A Requisição HTTP falhou: '.$lastException->getMessage();
            if ($error !== null) {
                return ['next' => $error, 'step_error' => $message, 'trace_data' => $traceData];
            }

            throw new TraceableFlowException($message, $traceData);
        }
        if ($response === null || $response->failed()) {
            $message = $response === null ? 'A Requisição HTTP falhou.' : "A Requisição HTTP falhou com status {$response->status()}.";
            if ($error !== null) {
                return ['next' => $error, 'step_error' => $message, 'trace_data' => $traceData];
            }

            throw new TraceableFlowException($message, $traceData);
        }

        $variables = $execution->variables;
        foreach ((array) ($config['response_map'] ?? $config['map'] ?? []) as $name => $path) {
            if (is_array($path)) {
                $name = $path['variable'] ?? $path['name'] ?? $name;
                $path = $path['path'] ?? $path['json_path'] ?? null;
            }
            if (is_string($path) && is_string($name)) {
                data_set($variables, $name, data_get($responseBody, $path));
            }
        }
        $execution->variables = $variables;
        $execution->save();

        return ['trace_data' => $traceData];
    }

    /** @param array<string, mixed> $config */
    private function sensitiveFields(array $config): array
    {
        $fields = $config['sensitive_fields']
            ?? $config['sensitiveFields']
            ?? $config['sensitive']
            ?? [];

        return is_array($fields) ? array_values(array_filter($fields, 'is_string')) : [];
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function templateNode(FlowExecution $execution, array $node, array $config): array
    {
        $channel = $execution->channel;
        $contact = $execution->contact;
        if ($channel === null || $contact === null) {
            throw new RuntimeException('O Nó de Template precisa de Canal e Contato.');
        }

        $purpose = ConsentPurpose::tryFrom(strtolower((string) ($config['purpose'] ?? $config['finality'] ?? '')));
        if ($purpose === null) {
            return $this->templateFailure($execution, $node, 'consent_purpose_missing', 'O Nó de Enviar Template precisa declarar uma finalidade de consentimento.');
        }

        $lastIncoming = $contact->messages()
            ->where('channel_id', $channel->getKey())
            ->where('direction', MessageDirection::Incoming)
            ->latest('created_at')
            ->latest('id')
            ->first();
        $outsideWindow = $lastIncoming === null || $this->customerCareWindow->hasExpired($channel, $lastIncoming);
        if ($outsideWindow && ! $this->consents->isGranted($contact, $purpose)) {
            return $this->templateFailure(
                $execution,
                $node,
                'consent_required_'.$purpose->value,
                'Envio recusado: o Contato não tem consentimento ativo para '.strtolower($purpose->label()).'.',
            );
        }

        $template = $this->templateForChannel($channel, $config);
        if ($template === null) {
            return $this->templateFailure($execution, $node, 'template_unavailable', 'O Template escolhido não está disponível neste Canal.');
        }
        if (! $template->isSendable()) {
            return $this->templateFailure($execution, $node, 'template_not_approved', 'O Template não está aprovado pela Meta e não pode ser enviado.');
        }

        $parameters = $this->templateParameters($config, $execution);
        $this->outputAdapterFactory->make($channel)->sendTemplate($channel, $contact, $template, $parameters);

        return [];
    }

    /** @param array<string, mixed> $config */
    private function templateForChannel(Channel $channel, array $config): ?WhatsAppTemplate
    {
        $templateId = $config['template_id'] ?? $config['templateId'] ?? null;
        if (! is_numeric($templateId)) {
            return null;
        }

        $channel->loadMissing('whatsappPhoneNumber.whatsappAccount');
        $account = $channel->whatsappPhoneNumber?->whatsappAccount;
        if ($account === null) {
            return null;
        }

        return WhatsAppTemplate::query()
            ->where('whatsapp_account_id', $account->getKey())
            ->find((int) $templateId);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    private function templateParameters(array $config, FlowExecution $execution): array
    {
        $configured = $config['variables'] ?? $config['parameters'] ?? [];
        if (! is_array($configured)) {
            return [];
        }

        return array_values(array_map(function (mixed $value) use ($execution): string {
            if (is_array($value)) {
                $source = strtolower((string) ($value['source'] ?? $value['type'] ?? ''));
                $path = (string) ($value['path'] ?? $value['value'] ?? $value['name'] ?? '');
                $path = match ($source) {
                    'variable', 'variables' => 'variables.'.$path,
                    'contact_field', 'field', 'contact' => 'field.'.$path,
                    'global', 'global_variable', 'globals' => 'global.'.$path,
                    default => $path,
                };
                $value = $this->resolveValue($path, $execution);
            } else {
                $value = $this->interpolateValue($value, $execution);
            }

            return is_scalar($value) ? (string) $value : '';
        }, $configured));
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function templateFailure(FlowExecution $execution, array $node, string $reason, string $message): array
    {
        $error = $this->edgeFor($execution, (string) $node['id'], 'error');
        if ($error !== null) {
            return ['next' => $error, 'step_error' => $reason.': '.$message];
        }

        return ['finish' => true, 'failed' => true, 'reason' => $reason, 'step_error' => $reason.': '.$message];
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
            return ['next' => $config['error'] ?? null, 'step_error' => 'subflow_unavailable'];
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

        $traceData = [];
        try {
            $history = $execution->contact?->history($execution->channel) ?? new Collection;
            $prompt = $this->interpolate((string) ($config['instruction'] ?? $config['prompt'] ?? ''), $execution);
            $response = (new WhatsAppAgent($agent, $history, $this->instructionTemplate, $this->knowledgeBaseSearch, null, $execution->contact))
                ->prompt($prompt, provider: config('ai.playground.provider'), model: config('ai.playground.model'), timeout: (int) config('ai.playground.timeout', 60));
            $traceData = $this->aiTraceData($prompt, $response, $config, $execution->variables);
            $value = trim($response->text);
            if (($config['structured'] ?? false) === true) {
                $value = json_decode($value, true, flags: JSON_THROW_ON_ERROR);
            }
            $variables = $execution->variables;
            data_set($variables, (string) ($config['variable'] ?? $config['output'] ?? 'ai_result'), $value);
            $execution->variables = $variables;
            $execution->save();

            return ['trace_data' => $traceData];
        } catch (Throwable $exception) {
            return [
                'next' => $config['error'] ?? null,
                'step_error' => $exception->getMessage(),
                'trace_data' => $traceData,
            ];
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

        $traceData = [];
        try {
            $history = $execution->contact?->history($execution->channel) ?? new Collection;
            $prompt = $message !== null ? $message->content : (string) ($execution->variables['message'] ?? '');
            $response = (new WhatsAppAgent($agent, $history, $this->instructionTemplate, $this->knowledgeBaseSearch, null, $execution->contact))
                ->prompt($prompt, provider: config('ai.playground.provider'), model: config('ai.playground.model'), timeout: (int) config('ai.playground.timeout', 60));
            $traceData = $this->aiTraceData($prompt, $response, $config, $execution->variables);
            $intent = trim($response->text);
            $variables = $execution->variables;
            data_set($variables, (string) ($config['variable'] ?? 'intent'), $intent);
            $execution->variables = $variables;
            $execution->save();

            foreach ((array) ($config['options'] ?? []) as $option) {
                if (is_array($option) && strcasecmp((string) ($option['name'] ?? ''), $intent) === 0) {
                    return ['next' => $option['target'] ?? null, 'trace_data' => $traceData];
                }
            }
        } catch (Throwable $exception) {
            return [
                'next' => $config['error'] ?? $config['none'] ?? null,
                'step_error' => $exception->getMessage(),
                'trace_data' => $traceData,
            ];
        }

        return ['next' => $config['none'] ?? null, 'trace_data' => $traceData];
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
        $variables = $execution->variables;
        data_set($variables, $retryKey, $retries + 1);
        data_set($variables, '__human_transfer_reason', $reason);
        $execution->variables = $variables;
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

    private function sendText(FlowExecution $execution, string $content, bool $allowOutsideWindow): bool
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
        $outsideWindow = $channel->type === ChannelType::WhatsApp
            && ($lastIncoming === null || $this->customerCareWindow->hasExpired($channel, $lastIncoming));
        if (! $allowOutsideWindow && $outsideWindow) {
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

            return false;
        }

        $this->outputAdapterFactory->make($channel)->sendText($channel, $contact, $content);

        return true;
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

    /**
     * @param  array<string, mixed>  $dsl
     * @return array<string, mixed>|null
     */
    private function edgeBetween(array $dsl, string $source, string $target): ?array
    {
        foreach ($this->edges($dsl, $source) as $edge) {
            if ($edge['target'] === $target) {
                return $edge;
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

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function withStepError(array $result, string $error): array
    {
        $result['step_error'] = $error;

        return $result;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function aiTraceData(string $prompt, AgentResponse $response, array $config, array $variables): array
    {
        $inputTokens = $response->usage->promptTokens;
        $outputTokens = $response->usage->completionTokens;
        $model = $response->meta->model ?? (string) config('ai.playground.model');
        $pricing = config('flows.ai_trace_pricing', []);
        $modelPricing = is_array($pricing['models'] ?? null) && is_array($pricing['models'][$model] ?? null)
            ? $pricing['models'][$model]
            : [];
        $inputPrice = (float) ($modelPricing['input_per_million_brl'] ?? $pricing['default_input_per_million_brl'] ?? 0);
        $outputPrice = (float) ($modelPricing['output_per_million_brl'] ?? $pricing['default_output_per_million_brl'] ?? 0);
        $cost = (($inputTokens / 1_000_000) * $inputPrice) + (($outputTokens / 1_000_000) * $outputPrice);
        $toolResults = $response->toolResults->keyBy('id');
        $toolCalls = $response->toolCalls->map(function (mixed $toolCall) use ($toolResults, $config): array {
            $call = method_exists($toolCall, 'toArray') ? $toolCall->toArray() : (array) $toolCall;
            $result = $toolResults->get($call['id'] ?? null);

            return [
                'call' => $this->traceSanitizer->sanitize($call, $this->sensitiveFields($config)),
                'result' => $result !== null && method_exists($result, 'toArray')
                    ? $this->traceSanitizer->sanitize($result->toArray(), $this->sensitiveFields($config))
                    : null,
            ];
        })->values()->all();

        return [
            'ai_prompt' => $this->traceSanitizer->sanitizeText($prompt, $this->sensitiveFields($config), $variables),
            'ai_response' => $this->traceSanitizer->sanitizeText($response->text, $this->sensitiveFields($config), $variables),
            'ai_input_tokens' => $inputTokens,
            'ai_output_tokens' => $outputTokens,
            'ai_cost_brl' => round($cost, 4),
            'ai_tool_calls' => $toolCalls,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $edge
     * @param  array<string, mixed>  $traceData
     * @param  array<string, mixed>  $nodeConfig
     */
    private function completeStep(
        FlowExecutionStep $step,
        FlowExecution $execution,
        string $result,
        int $startedAtNanoseconds,
        ?string $error = null,
        ?array $edge = null,
        array $traceData = [],
        array $nodeConfig = [],
    ): void {
        $source = is_string($edge['source'] ?? null) ? $edge['source'] : null;
        $target = is_string($edge['target'] ?? null) ? $edge['target'] : null;
        $edgeId = is_string($edge['id'] ?? null)
            ? $edge['id']
            : ($source !== null && $target !== null ? "{$source}->{$target}" : null);
        $edgeLabel = $edge['label'] ?? $edge['condition'] ?? null;
        $aiCost = is_numeric($traceData['ai_cost_brl'] ?? null) ? (float) $traceData['ai_cost_brl'] : null;
        $sanitizedVariables = $this->traceSanitizer->sanitizeVariables($execution->variables, $nodeConfig);

        $step->forceFill([
            'result' => $result,
            'duration_ms' => max(0, (int) round((hrtime(true) - $startedAtNanoseconds) / 1_000_000)),
            'edge_id' => $edgeId,
            'edge_source_node_id' => $source,
            'edge_target_node_id' => $target,
            'edge_label' => is_scalar($edgeLabel) ? (string) $edgeLabel : null,
            'error' => $error,
            'variables_snapshot' => $sanitizedVariables,
            'http_request' => is_array($traceData['http_request'] ?? null) ? $traceData['http_request'] : null,
            'http_response' => is_array($traceData['http_response'] ?? null) ? $traceData['http_response'] : null,
            'http_status' => is_numeric($traceData['http_status'] ?? null) ? (int) $traceData['http_status'] : null,
            'retry_count' => is_numeric($traceData['retry_count'] ?? null) ? (int) $traceData['retry_count'] : 0,
            'ai_prompt' => is_string($traceData['ai_prompt'] ?? null) ? $traceData['ai_prompt'] : null,
            'ai_response' => is_string($traceData['ai_response'] ?? null) ? $traceData['ai_response'] : null,
            'ai_input_tokens' => is_numeric($traceData['ai_input_tokens'] ?? null) ? (int) $traceData['ai_input_tokens'] : null,
            'ai_output_tokens' => is_numeric($traceData['ai_output_tokens'] ?? null) ? (int) $traceData['ai_output_tokens'] : null,
            'ai_cost_brl' => $aiCost,
            'ai_tool_calls' => is_array($traceData['ai_tool_calls'] ?? null) ? $traceData['ai_tool_calls'] : null,
        ])->save();

        if ($aiCost !== null) {
            $execution->forceFill([
                'ai_cost_brl' => (float) $execution->ai_cost_brl + $aiCost,
            ])->save();
        }
        $this->aggregator->recordStep($execution, $step);
    }

    private function finishExecution(FlowExecution $execution, FlowExecutionStatus $status, string $reason): void
    {
        $execution->finish(
            $status,
            $this->traceSanitizer->sanitizeText($reason, [], $execution->variables),
        );
        $this->aggregator->recordFinished($execution, $status);
    }

    /** @param array<string, mixed> $node */
    private function nodeConfig(array $node): array
    {
        return is_array($node['config'] ?? null) ? $node['config'] : [];
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
            $this->finishExecution($execution, FlowExecutionStatus::Completed, 'replaced_by_new_trigger');
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
