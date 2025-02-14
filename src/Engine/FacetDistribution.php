<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Engine;

use Webmozart\Assert\Assert;

/**
 * @implements \IteratorAggregate<string, FacetValues>
 * @implements \ArrayAccess<string, FacetValues>
 */
final class FacetDistribution implements \Countable, \IteratorAggregate, \ArrayAccess
{
    /** @var array<string, FacetValues> */
    private array $facetValues = [];

    /**
     * @param array<string, mixed> $facetDistribution
     */
    public function __construct(array $facetDistribution)
    {
        foreach ($facetDistribution as $facet => $facetValues) {
            Assert::isArray($facetValues);

            $this->facetValues[$facet] = new FacetValues($facet, $facetValues);
        }
    }

    /**
     * @psalm-assert-if-true FacetValues $this->facetValues[$facet]
     */
    public function has(string $facet): bool
    {
        return isset($this->facetValues[$facet]);
    }

    public function get(string $facet): FacetValues
    {
        if (!$this->has($facet)) {
            throw new \InvalidArgumentException(sprintf('Facet "%s" does not exist', $facet));
        }

        return $this->facetValues[$facet];
    }

    /**
     * @return \ArrayIterator<string, FacetValues>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->facetValues);
    }

    public function count(): int
    {
        return count($this->facetValues);
    }

    public function isEmpty(): bool
    {
        return [] === $this->facetValues;
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->has($offset);
    }

    public function offsetGet(mixed $offset): FacetValues
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
