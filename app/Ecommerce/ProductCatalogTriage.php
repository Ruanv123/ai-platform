<?php

namespace App\Ecommerce;

use App\Ai\Agents\ProductTriageAgent;
use App\Enums\MessageDirection;
use App\Models\Agent as ConfiguredAgent;
use App\Models\AgentContactMemory;
use App\Models\Contact;
use App\Models\Message as ConversationMessage;
use App\Models\ProductVariant;
use App\Models\StoreCustomer;
use App\Models\StoreOrder;
use App\Models\Team;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ProductCatalogTriage
{
    public function select(
        Team $team,
        ConfiguredAgent $agent,
        ?Contact $contact,
        array|Collection $conversationMessages,
        string $query,
        EloquentCollection $candidates,
    ): array {
        $offerLimit = min(max((int) config('knowledge.product_search.offer_limit', 5), 3), 5);
        $offeredCandidates = $candidates->take($offerLimit)->values();

        if ($candidates->count() <= $offerLimit) {
            return [
                'variants' => $offeredCandidates,
                'reasons' => [],
                'trace' => ['used' => false],
            ];
        }

        $provider = (string) config('ai.product_triage.provider', config('ai.playground.provider', 'openai'));
        $configuredModel = (string) config('ai.product_triage.model', 'gpt-5.6-luna');
        $trace = [
            'used' => true,
            'status' => 'fallback',
            'model' => $configuredModel,
            'input_tokens' => null,
            'output_tokens' => null,
            'cost_brl' => null,
        ];

        try {
            $response = (new ProductTriageAgent)->prompt(
                $this->prompt($team, $agent, $contact, $conversationMessages, $query, $candidates),
                provider: $provider,
                model: $configuredModel,
                timeout: (int) config('ai.product_triage.timeout', 20),
            );
            $model = $response->meta->model ?? $configuredModel;
            $inputTokens = $response->usage->promptTokens;
            $outputTokens = $response->usage->completionTokens;
            $cost = $this->cost($model, $inputTokens, $outputTokens);
            $recommendations = is_array($response['recommendations'] ?? null)
                ? $response['recommendations']
                : [];
            $candidateMap = $candidates->keyBy(fn (ProductVariant $variant): string => (string) $variant->getKey());
            $selected = collect($recommendations)
                ->filter(fn (mixed $recommendation): bool => is_array($recommendation)
                    && is_string($recommendation['variant_id'] ?? null)
                    && $candidateMap->has($recommendation['variant_id']))
                ->unique('variant_id')
                ->take($offerLimit)
                ->map(function (array $recommendation) use ($candidateMap): array {
                    $reason = $this->plainText(is_string($recommendation['reason'] ?? null) ? $recommendation['reason'] : '', 180);

                    return [
                        'variant' => $candidateMap->get($recommendation['variant_id']),
                        'reason' => $reason !== '' ? $reason : 'Combina com o que você procura.',
                    ];
                })
                ->values();

            $trace = [
                'used' => true,
                'status' => $selected->count() >= 3 ? 'completed' : 'fallback',
                'model' => $model,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'cost_brl' => $cost,
            ];

            if ($selected->count() < 3) {
                return [
                    'variants' => $offeredCandidates,
                    'reasons' => [],
                    'trace' => $trace,
                ];
            }

            $this->rememberContactPreferences(
                $team,
                $agent,
                $contact,
                is_string($response['contact_preferences'] ?? null) ? $response['contact_preferences'] : '',
            );

            return [
                'variants' => $selected->pluck('variant'),
                'reasons' => $selected->mapWithKeys(fn (array $recommendation): array => [
                    (string) $recommendation['variant']->getKey() => $recommendation['reason'],
                ])->all(),
                'trace' => $trace,
            ];
        } catch (Throwable $exception) {
            Log::warning('Product triage failed; catalog ranking was retained.', [
                'team_id' => $team->getKey(),
                'agent_id' => $agent->getKey(),
                'exception' => $exception::class,
            ]);

            return [
                'variants' => $offeredCandidates,
                'reasons' => [],
                'trace' => $trace,
            ];
        }
    }

    private function prompt(
        Team $team,
        ConfiguredAgent $agent,
        ?Contact $contact,
        array|Collection $conversationMessages,
        string $query,
        EloquentCollection $candidates,
    ): string {
        $candidateData = $candidates
            ->values()
            ->map(fn (ProductVariant $variant, int $index): array => [
                'search_rank' => $index + 1,
                'variant_id' => (string) $variant->getKey(),
                'product' => $this->plainText((string) $variant->product_title, 180),
                'variant' => $this->plainText((string) $variant->variant_title, 180),
                'options' => $variant->options,
                'price' => $variant->price,
                'compare_at_price' => $variant->compare_at_price,
                'available' => (bool) $variant->available,
                'continues_selling_when_out_of_stock' => (bool) $variant->continues_selling_when_out_of_stock,
            ])
            ->all();

        return json_encode([
            'search' => $this->plainText($query, 500),
            'recent_conversation' => $this->conversation($conversationMessages),
            'store_orders' => $contact === null ? [] : $this->orders($team, $contact),
            'agent_memory' => $this->memory($team, $agent, $contact),
            'candidates' => $candidateData,
            'maximum_recommendations' => min(max((int) config('knowledge.product_search.offer_limit', 5), 3), 5),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function conversation(array|Collection $conversationMessages): array
    {
        return collect($conversationMessages)
            ->slice(-20)
            ->map(function (mixed $message): ?array {
                if ($message instanceof ConversationMessage) {
                    $role = $message->direction === MessageDirection::Outgoing ? 'assistant' : 'user';
                    $content = $message->content ?? "[Mensagem {$message->payload_type} recebida sem conteúdo textual.]";

                    return ['role' => $role, 'content' => $this->plainText($content, 800)];
                }

                if (is_array($message)
                    && in_array($message['role'] ?? null, ['assistant', 'user'], true)
                    && is_string($message['content'] ?? null)) {
                    return [
                        'role' => $message['role'],
                        'content' => $this->plainText($message['content'], 800),
                    ];
                }

                return null;
            })
            ->filter()
            ->values()
            ->all();
    }

    private function orders(Team $team, Contact $contact): array
    {
        if (! $contact->exists || $contact->team_id !== $team->getKey()) {
            return [];
        }

        $shopIds = $team->shops()->pluck('shops.id');
        if ($shopIds->isEmpty()) {
            return [];
        }

        $storeCustomerId = StoreCustomer::withoutGlobalScopes()
            ->where('team_id', $team->getKey())
            ->where('contact_id', $contact->getKey())
            ->whereIn('shop_id', $shopIds)
            ->value('id');

        if ($storeCustomerId === null) {
            return [];
        }

        return StoreOrder::withoutGlobalScopes()
            ->where('team_id', $team->getKey())
            ->where('store_customer_id', $storeCustomerId)
            ->whereIn('shop_id', $shopIds)
            ->orderByDesc('ordered_at')
            ->orderByDesc('id')
            ->limit(10)
            ->with(['items' => fn ($items) => $items->where('team_id', $team->getKey())->orderBy('id')->limit(8)])
            ->get()
            ->map(fn (StoreOrder $order): array => [
                'order_number' => $order->order_number,
                'ordered_at' => $order->ordered_at?->toIso8601String(),
                'total_amount' => $order->total_amount,
                'currency_code' => $order->currency_code,
                'financial_status' => $order->financial_status,
                'fulfillment_status' => $order->fulfillment_status,
                'items' => $order->items->map(fn ($item): array => [
                    'title' => $this->plainText($item->title, 160),
                    'quantity' => (int) $item->quantity,
                    'unit_price' => $item->unit_price,
                ])->all(),
            ])
            ->all();
    }

    private function memory(Team $team, ConfiguredAgent $agent, ?Contact $contact): string
    {
        if (! $agent->exists
            || $agent->team_id !== $team->getKey()
            || $contact === null
            || ! $contact->exists
            || $contact->team_id !== $team->getKey()) {
            return '';
        }

        $content = AgentContactMemory::withoutGlobalScopes()
            ->where('team_id', $team->getKey())
            ->where('agent_id', $agent->getKey())
            ->where('contact_id', $contact->getKey())
            ->value('content');

        return is_string($content) ? $this->plainText($content, 2000) : '';
    }

    private function rememberContactPreferences(Team $team, ConfiguredAgent $agent, ?Contact $contact, string $preferences): void
    {
        $preferences = $this->plainText($preferences, 2000);

        if ($preferences === ''
            || ! $agent->exists
            || $agent->team_id !== $team->getKey()
            || $contact === null
            || ! $contact->exists
            || $contact->team_id !== $team->getKey()) {
            return;
        }

        AgentContactMemory::withoutGlobalScopes()->updateOrCreate(
            [
                'team_id' => $team->getKey(),
                'agent_id' => $agent->getKey(),
                'contact_id' => $contact->getKey(),
            ],
            ['content' => $preferences],
        );
    }

    private function plainText(string $value, int $limit): string
    {
        return Str::limit(Str::squish(strip_tags($value)), $limit);
    }

    private function cost(string $model, int $inputTokens, int $outputTokens): float
    {
        $pricing = config('flows.ai_trace_pricing', []);
        $modelPricing = is_array($pricing['models'] ?? null) && is_array($pricing['models'][$model] ?? null)
            ? $pricing['models'][$model]
            : [];
        $inputPrice = (float) ($modelPricing['input_per_million_brl'] ?? $pricing['default_input_per_million_brl'] ?? 0);
        $outputPrice = (float) ($modelPricing['output_per_million_brl'] ?? $pricing['default_output_per_million_brl'] ?? 0);

        return round(($inputTokens / 1_000_000 * $inputPrice) + ($outputTokens / 1_000_000 * $outputPrice), 4);
    }
}
