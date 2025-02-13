<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Engine;

use Webmozart\Assert\Assert;

/**
 * @implements \IteratorAggregate<string, FacetStat>
 * @implements \ArrayAccess<string, FacetStat>
 */
final class FacetStats implements \Countable, \IteratorAggregate, \ArrayAccess
{
    /** @var array<string, FacetStat> */
    private array $facetStats = [];

    /**
     * @param array<string, mixed> $facetStats
     */
    public function __construct(array $facetStats)
    {
        foreach ($facetStats as $facet => $facetStat) {
            Assert::isArray($facetStat);

            $this->facetStats[$facet] = new FacetStat($facet, $facetStat);
        }
    }

    /**
     * @psalm-assert-if-true FacetStat $this->facetStats[$facet]
     */
    public function has(string $facet): bool
    {
        return isset($this->facetStats[$facet]);
    }

    public function get(string $facet): FacetStat
    {
        if (!$this->has($facet)) {
            throw new \InvalidArgumentException(sprintf('Facet "%s" does not exist', $facet));
        }

        return $this->facetStats[$facet];
    }

    /**
     * @return \ArrayIterator<string, FacetStat>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->facetStats);
    }

    public function count(): int
    {
        return count($this->facetStats);
    }

    public function isEmpty(): bool
    {
        return [] === $this->facetStats;
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->has($offset);
    }

    public function offsetGet(mixed $offset): FacetStat
    {
        return $this->get($offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \LogicException('You cannot set an offset');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new \LogicException('You cannot unset an offset');
    }
}
