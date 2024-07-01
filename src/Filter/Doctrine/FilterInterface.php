<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Filter\Doctrine;

use Doctrine\ORM\QueryBuilder;
use Setono\SyliusMeilisearchPlugin\Config\IndexableResource;

/**
 * Use this filter to apply filters when selecting candidates for indexing
 */
interface FilterInterface
{
    /**
     * Will apply a filter to the given query builder instance
     */
    public function apply(QueryBuilder $qb, IndexableResource $indexableResource): void;
}
