<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\DataMapper\Product\Provider;

use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScope;
use Sylius\Component\Product\Model\ProductInterface;

interface DataMapperValuesProviderInterface
{
    /**
     * @return array<string, bool|float|int|string|list<string>>
     */
    public function provide(ProductInterface $source, IndexScope $indexScope): array;
}
