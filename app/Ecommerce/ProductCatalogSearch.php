<?php

namespace App\Ecommerce;

use App\Models\ProductVariant;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Str;
use Laravel\Ai\Embeddings;

class ProductCatalogSearch
{
    public function search(
        Team $team,
        string $query,
        ?float $minimumPrice = null,
        ?float $maximumPrice = null,
        ?string $category = null,
        ?string $option = null,
        bool $availableOnly = false,
    ): Collection {
        $query = Str::squish($query);

        if ($query === '' || ($minimumPrice !== null && $maximumPrice !== null && $minimumPrice > $maximumPrice)) {
            return new Collection;
        }

        $embedding = Embeddings::for([$query])
            ->dimensions((int) config('knowledge.embedding_dimensions', 1536))
            ->generate(
                config('knowledge.embedding_provider', 'openai'),
                config('knowledge.embedding_model', 'text-embedding-3-small'),
            )
            ->first();

        $minimumSimilarity = (float) config('knowledge.search.min_similarity', 0.35);
        $resultLimit = min(max((int) config('knowledge.search.top_k', 5), 1), 10);
        $teamId = $team->getKey();

        return ProductVariant::withoutGlobalScopes()
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->join('shops', 'shops.id', '=', 'product_variants.shop_id')
            ->where('product_variants.team_id', $teamId)
            ->where('products.team_id', $teamId)
            ->where('shops.team_id', $teamId)
            ->whereIn('product_variants.shop_id', $team->shops()->select('shops.id'))
            ->whereColumn('products.shop_id', 'product_variants.shop_id')
            ->where('products.status', 'ACTIVE')
            ->where('products.online_store_published', true)
            ->whereNotNull('products.embedding')
            ->where(function (Builder $variantQuery): void {
                $variantQuery
                    ->where('product_variants.available', true)
                    ->orWhere('product_variants.continues_selling_when_out_of_stock', true);
            })
            ->when($availableOnly, fn (Builder $variantQuery): Builder => $variantQuery->where('product_variants.available', true))
            ->when($minimumPrice !== null, fn (Builder $variantQuery): Builder => $variantQuery->where('product_variants.price', '>=', $minimumPrice))
            ->when($maximumPrice !== null, fn (Builder $variantQuery): Builder => $variantQuery->where('product_variants.price', '<=', $maximumPrice))
            ->when(filled($category), function (Builder $variantQuery) use ($category, $teamId): void {
                $variantQuery->whereExists(function (QueryBuilder $categoryQuery) use ($category, $teamId): void {
                    $categoryQuery
                        ->selectRaw('1')
                        ->from('category_product')
                        ->join('categories', 'categories.id', '=', 'category_product.category_id')
                        ->whereColumn('category_product.product_id', 'products.id')
                        ->where('category_product.team_id', $teamId)
                        ->where('categories.team_id', $teamId)
                        ->where('categories.title', 'ilike', '%'.Str::squish($category).'%');
                });
            })
            ->when(filled($option), function (Builder $variantQuery) use ($option): void {
                $tokens = collect(preg_split('/\s+/', Str::lower(Str::squish($option)), flags: PREG_SPLIT_NO_EMPTY));

                foreach ($tokens as $token) {
                    $variantQuery->whereRaw('product_variants.options::text ILIKE ?', ['%'.addcslashes($token, '\\%_').'%']);
                }
            })
            ->select([
                'product_variants.id',
                'product_variants.title as variant_title',
                'product_variants.options',
                'product_variants.price',
                'product_variants.compare_at_price',
                'product_variants.available',
                'product_variants.continues_selling_when_out_of_stock',
                'products.title as product_title',
                'products.url as product_url',
            ])
            ->selectVectorDistance('products.embedding', $embedding, as: 'distance')
            ->whereVectorDistanceLessThan('products.embedding', $embedding, 1 - $minimumSimilarity + 1e-6)
            ->orderByVectorDistance('products.embedding', $embedding)
            ->orderBy('product_variants.id')
            ->limit($resultLimit)
            ->get();
    }
}
