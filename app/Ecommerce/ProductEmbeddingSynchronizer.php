<?php

namespace App\Ecommerce;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Embeddings;

final class ProductEmbeddingSynchronizer
{
    public function refresh(string $productId): void
    {
        $product = Product::withoutGlobalScopes()->whereKey($productId)->first();

        if ($product === null) {
            return;
        }

        $categoryTitles = DB::table('category_product')
            ->join('categories', 'categories.id', '=', 'category_product.category_id')
            ->where('category_product.product_id', $product->getKey())
            ->pluck('categories.title')
            ->filter()
            ->unique()
            ->sort()
            ->implode(', ');
        $options = ProductVariant::withoutGlobalScopes()
            ->where('product_id', $product->getKey())
            ->get(['options'])
            ->flatMap(fn (ProductVariant $variant): array => collect($variant->options ?? [])
                ->filter(fn (mixed $option): bool => is_array($option)
                    && is_string($option['name'] ?? null)
                    && is_string($option['value'] ?? null))
                ->map(fn (array $option): string => $option['name'].': '.$option['value'])
                ->all())
            ->unique()
            ->sort()
            ->implode('; ');
        $sourceText = implode("\n", array_filter([
            'Produto: '.$product->title,
            $product->description,
            $categoryTitles === '' ? null : 'Categorias: '.$categoryTitles,
            $options === '' ? null : 'Opções: '.$options,
        ], fn (?string $value): bool => $value !== null && $value !== ''));
        $sourceHash = hash('sha256', $sourceText);

        if (is_string($product->source_hash) && hash_equals($product->source_hash, $sourceHash)) {
            return;
        }

        $embedding = Embeddings::for([$sourceText])
            ->dimensions((int) config('knowledge.embedding_dimensions', 1536))
            ->generate(
                config('knowledge.embedding_provider', 'openai'),
                config('knowledge.embedding_model', 'text-embedding-3-small'),
            )
            ->first();

        Product::withoutGlobalScopes()->whereKey($product->getKey())->update([
            'embedding' => $embedding,
            'source_hash' => $sourceHash,
        ]);
    }
}
