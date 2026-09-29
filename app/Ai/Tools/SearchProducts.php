<?php

namespace App\Ai\Tools;

use App\Ecommerce\ProductCatalogSearch;
use App\Ecommerce\ProductCatalogTriage;
use App\Models\Agent as ConfiguredAgent;
use App\Models\Contact;
use App\Models\ProductVariant;
use App\Models\Team;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class SearchProducts implements Tool
{
    public function __construct(
        private Team $team,
        private ProductCatalogSearch $productCatalogSearch,
        private ProductCatalogTriage $productCatalogTriage,
        private ConfiguredAgent $agent,
        private ?Contact $contact,
        private array|Collection $conversationMessages,
    ) {}

    public function description(): Stringable|string
    {
        return 'Busque produtos reais da loja que correspondam ao que o Contato procura. Use esta ferramenta para confirmar preços, opções, disponibilidade e links antes de recomendar itens. O campo triage_trace contém métricas internas e não deve ser compartilhado com o Contato.';
    }

    public function handle(Request $request): Stringable|string
    {
        $arguments = $request->validate([
            'query' => ['required', 'string', 'max:1000'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0'],
            'category' => ['nullable', 'string', 'max:255'],
            'option' => ['nullable', 'string', 'max:255'],
            'available_only' => ['nullable', 'boolean'],
        ]);

        $results = $this->productCatalogSearch->search(
            $this->team,
            $arguments['query'],
            isset($arguments['min_price']) ? (float) $arguments['min_price'] : null,
            isset($arguments['max_price']) ? (float) $arguments['max_price'] : null,
            $arguments['category'] ?? null,
            $arguments['option'] ?? null,
            (bool) ($arguments['available_only'] ?? false),
            min(max((int) config('knowledge.product_search.candidate_limit', 30), 1), 30),
        );
        $triage = $this->productCatalogTriage->select(
            $this->team,
            $this->agent,
            $this->contact,
            $this->conversationMessages,
            $arguments['query'],
            $results,
        );

        return json_encode([
            'consulted' => true,
            'results' => $triage['variants']
                ->map(fn (ProductVariant $variant): array => [
                    'variant_id' => $variant->id,
                    'title' => $variant->product_title,
                    'variant' => $variant->variant_title,
                    'options' => $variant->options,
                    'price' => $variant->price,
                    'compare_at_price' => $variant->compare_at_price,
                    'available' => (bool) $variant->available,
                    'continues_selling_when_out_of_stock' => (bool) $variant->continues_selling_when_out_of_stock,
                    'availability' => $variant->available ? 'Disponível' : 'Vendido mesmo sem estoque',
                    'url' => $variant->product_url,
                    ...isset($triage['reasons'][(string) $variant->getKey()])
                        ? ['recommendation_reason' => $triage['reasons'][(string) $variant->getKey()]]
                        : [],
                ])
                ->values()
                ->all(),
            'triage_trace' => $triage['trace'],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Descrição natural do produto que o Contato procura.')->required(),
            'min_price' => $schema->number()->nullable()->description('Preço mínimo inclusivo.'),
            'max_price' => $schema->number()->nullable()->description('Preço máximo inclusivo.'),
            'category' => $schema->string()->nullable()->description('Nome ou parte do nome da Categoria.'),
            'option' => $schema->string()->nullable()->description('Opção desejada, como tamanho 42 ou cor azul.'),
            'available_only' => $schema->boolean()->nullable()->description('Quando verdadeiro, retorna apenas variantes disponíveis agora.'),
        ];
    }

    public function name(): string
    {
        return 'search_products';
    }
}
