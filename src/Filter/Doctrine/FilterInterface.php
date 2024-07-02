<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Filter\Doctrine;

use Doctrine\ORM\QueryBuilder;
use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;

/**
 * Use this filter to apply filters when selecting candidates for indexing
 */
interface FilterInterface
{
    /**
     * Will apply a filter to the given query builder instance
     *
     * @param class-string<IndexableInterface> $entity
     */
    public function apply(QueryBuilder $qb, string $entity): void;
}
