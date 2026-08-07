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

    /**
     * Returns true if the object with the given id would be part of the set returned by getIds().
     *
     * Implementations MUST keep this consistent with getIds(): an id is contained if and only if
     * getIds() would yield it. The incremental (single-entity) indexing path relies on this to decide
     * whether a changed entity should be indexed or have its document removed, so this is the single
     * source of truth shared with a full reindex.
     *
     * @param class-string<IndexableInterface> $entity
     */
    public function containsId(string $entity, Index $index, int|string $id): bool;
}
