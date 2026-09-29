<?php

namespace App\Enums;

enum ShopifyBulkOperationKind: string
{
    case Catalog = 'catalog';
    case Customers = 'customers';
    case Orders = 'orders';

    public function next(): ?self
    {
        return match ($this) {
            self::Catalog => self::Customers,
            self::Customers => self::Orders,
            self::Orders => null,
        };
    }

    public function initialProgress(): int
    {
        return match ($this) {
            self::Catalog => 0,
            self::Customers => 33,
            self::Orders => 66,
        };
    }

    public function progress(int $processedCount, ?int $totalCount): int
    {
        $range = 33;
        $count = $totalCount ?? 0;
        $phaseProgress = $count < 1
            ? 0
            : (int) floor(min(1, max(0, $processedCount / $count)) * $range);

        return min(99, $this->initialProgress() + $phaseProgress);
    }
}
