<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\DataMapper\Product\Provider;

use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScope;

interface DataMapperValuesProviderInterface
{
    /**
     * @return array<string, bool|float|int|string|list<string>>
     */
    public function provide(IndexableInterface $source, IndexScope $indexScope): array;
}
