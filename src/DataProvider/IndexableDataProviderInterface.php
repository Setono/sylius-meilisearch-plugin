<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\DataProvider;

use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;

interface IndexableDataProviderInterface
{
    /**
     * Returns ids of objects of the given entity that should be indexed
     *
     * @param class-string<IndexableInterface> $entity
     *
     * @return iterable<array-key, string|int>
     */
    public function getIds(string $entity, Index $index): iterable;
}
