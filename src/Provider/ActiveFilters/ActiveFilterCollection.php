<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Provider\ActiveFilters;

/**
 * @implements \IteratorAggregate<int, ActiveFilter>
 */
final class ActiveFilterCollection implements \Countable, \IteratorAggregate
{
    public function __construct(
        /** @var list<ActiveFilter> $filters */
        public readonly array $filters = [],

        /** The url that will remove all filters from the search while keeping the query and sorting */
        public readonly ?string $resetUrl = null,
    ) {
    }

    /**
     * @return \ArrayIterator<int, ActiveFilter>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->filters);
    }

    public function count(): int
    {
        return count($this->filters);
    }

    public function isEmpty(): bool
    {
        return [] === $this->filters;
    }
}
