<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\DataMapper\Product\Provider;

final class ProductPrices
{
    public function __construct(
        public readonly int $price,
        public readonly ?int $originalPrice,
    ) {
    }
}