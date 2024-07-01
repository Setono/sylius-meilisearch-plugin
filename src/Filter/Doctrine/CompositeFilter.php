<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Filter\Doctrine;

use Doctrine\ORM\QueryBuilder;

final class CompositeFilter implements FilterInterface
{
    /** @var list<FilterInterface> */
    private array $filters = [];

    public function add(FilterInterface $filter): void
    {
        $this->filters[] = $filter;
    }

    public function apply(QueryBuilder $qb, string $entity): void
    {
        foreach ($this->filters as $filter) {
            $filter->apply($qb, $entity);
        }
    }
}
