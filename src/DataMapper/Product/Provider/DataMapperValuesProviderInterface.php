<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\DataMapper\Product\Provider;

use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;

interface DataMapperValuesProviderInterface
{
    public function provide(IndexableInterface $source, array $context = []): array;
}
