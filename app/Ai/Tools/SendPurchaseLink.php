<?php

namespace App\Ai\Tools;

use App\Models\Agent;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\ProductVariant;
use App\Models\PurchaseAttribution;
use App\Models\Shop;
use App\Models\Team;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class SendPurchaseLink implements Tool
{
    public function __construct(
        private Team $team,
        private Agent $agent,
        private ?Contact $contact,
        private ?Channel $channel,
    ) {}

    public function description(): Stringable|string
    {
        return 'Gere um link de compra para as Variantes que o Contato escolheu. Use os variant_id retornados por search_products e confirme a quantidade de cada Variante. Variantes indisponíveis e Produtos não publicados não podem ser comprados.';
    }

    public function handle(Request $request): Stringable|string
    {
        try {
            $arguments = $request->validate([
                'items' => ['required', 'array', 'min:1', 'max:20'],
                'items.*.variant_id' => ['required', 'uuid', 'distinct'],
                'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
            ]);
        } catch (ValidationException $exception) {
            return $this->json([
                'ok' => false,
                'message' => $exception->validator->errors()->first(),
            ]);
        }

        $shop = $this->shop();
        if ($shop === null) {
            return $this->failure('A Loja Shopify não está conectada.');
        }

        if (! preg_match('/\A[a-z0-9][a-z0-9-]*\.myshopify\.com\z/i', $shop->domain)) {
            return $this->failure('O domínio da Loja Shopify está inválido.');
        }

        $items = $arguments['items'];
        $variantIds = array_column($items, 'variant_id');
        $variants = ProductVariant::query()
            ->where('team_id', $this->team->getKey())
            ->where('shop_id', $shop->getKey())
            ->whereIn('id', $variantIds)
            ->with('product')
            ->get()
            ->keyBy('id');

        if ($variants->count() !== count($items)) {
            return $this->failure('Uma das Variantes não pertence à Loja conectada. Busque novamente o catálogo.');
        }

        $lineItems = [];
        foreach ($items as $item) {
            $variant = $variants->get($item['variant_id']);
            $product = $variant?->product;

            if ($variant === null || $product === null || $product->team_id !== $this->team->getKey() || $product->shop_id !== $shop->getKey()) {
                return $this->failure('Uma das Variantes não pertence à Loja conectada. Busque novamente o catálogo.');
            }

            if ($product->status !== 'ACTIVE' || ! $product->online_store_published) {
                return $this->failure('Um dos Produtos escolhidos não está publicado para compra. Busque novamente o catálogo.');
            }

            if (! $variant->available && ! $variant->continues_selling_when_out_of_stock) {
                return $this->failure('Uma das Variantes escolhidas está indisponível. Escolha outra opção disponível.');
            }

            if (! preg_match('/\Agid:\/\/shopify\/ProductVariant\/([0-9]+)\z/', $variant->external_id, $matches)) {
                return $this->failure('Uma das Variantes não tem um identificador válido para compra. Busque novamente o catálogo.');
            }

            $lineItems[] = $matches[1].':'.$item['quantity'];
        }

        if ($this->channel !== null && ! $this->hasValidAttributionContext()) {
            return $this->failure('Não foi possível associar o Link de compra a este Contato e Canal.');
        }

        $token = $this->channel === null ? null : Str::random(64);
        $query = [
            'utm_source' => 'ia-platform',
            'utm_medium' => 'whatsapp',
            'utm_campaign' => 'agente-ia',
        ];
        $email = $this->contact?->storeCustomer?->email;

        if (is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
            $query['checkout'] = ['email' => $email];
        }

        if ($token !== null) {
            $query['attributes'] = ['_platform_attribution' => $token];
        }

        $url = 'https://'.$shop->domain.'/cart/'.implode(',', $lineItems).'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);

        if ($token !== null) {
            PurchaseAttribution::query()->create([
                'team_id' => $this->team->getKey(),
                'shop_id' => $shop->getKey(),
                'contact_id' => $this->contact->getKey(),
                'agent_id' => $this->agent->getKey(),
                'channel_id' => $this->channel->getKey(),
                'token_hash' => hash('sha256', $token),
            ]);
        }

        return $this->json([
            'ok' => true,
            'url' => $url,
            'items' => count($lineItems),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'items' => $schema->array()
                ->min(1)
                ->max(20)
                ->items($schema->object([
                    'variant_id' => $schema->string()->description('ID interno retornado por search_products.')->required(),
                    'quantity' => $schema->integer()->min(1)->max(999)->description('Quantidade escolhida pelo Contato.')->required(),
                ])->withoutAdditionalProperties())
                ->required(),
        ];
    }

    public function name(): string
    {
        return 'send_purchase_link';
    }

    private function shop(): ?Shop
    {
        return $this->team->shops()
            ->whereHas('integration', fn ($query) => $query->where('provider', 'shopify'))
            ->first();
    }

    private function hasValidAttributionContext(): bool
    {
        return $this->contact?->exists === true
            && $this->agent->exists
            && $this->channel?->exists === true
            && $this->contact->team_id === $this->team->getKey()
            && $this->agent->team_id === $this->team->getKey()
            && $this->channel->team_id === $this->team->getKey()
            && $this->channel->agent_id === $this->agent->getKey();
    }

    private function failure(string $message): string
    {
        return $this->json(['ok' => false, 'message' => $message]);
    }

    private function json(array $payload): string
    {
        return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
