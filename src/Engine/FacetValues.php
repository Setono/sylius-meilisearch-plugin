<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Engine;

/**
 * @implements \ArrayAccess<string, int>
 * @implements \IteratorAggregate<string, int>
 */
final class FacetValues implements \Countable, \IteratorAggregate, \ArrayAccess
{
    /**
     * This holds the facet values for the respective facet and search results. Examples for $values include:
     *
     * [
     *   "Celsius Small" => 3
     *   "Date & Banana" => 2
     *   "Modern Wear" => 6
     *   "You are breathtaking" => 10
     * ]
     *
     * [
     *   "false" => 16
     *   "true" => 5
     * ]
     *
     * [
     *   "1.74" => 1
     *   "12.21" => 1
     *   "16.52" => 1
     *   "21.06" => 1
     *   "22.49" => 1
     *   "24.19" => 1
     * ]
     *
     * @var array<string, int>
     */
    private array $values = [];

    public function __construct(
        public readonly string $name,
        array $values,
        public readonly ?FacetStats $stats = null,
    ) {
        foreach ($values as $key => $value) {
            if (!is_int($value)) {
                throw new \InvalidArgumentException(sprintf(
                    'The $values array must be an array of strings and integers. Input was: %s',
                    json_encode($values, \JSON_THROW_ON_ERROR),
                ));
            }

            $this->values[(string) $key] = $value;
        }
    }

    /**
     * @return list<string>
     */
    public function getValues(): array
    {
        return array_keys($this->values);
    }

    public function getValueCount(string $value): int
    {
        if (!$this->has($value)) {
            throw new \InvalidArgumentException(sprintf('Facet value "%s" does not exist', $value));
        }

        return $this->values[$value];
    }

    /**
     * @psalm-assert-if-true int $this->values[$value]
     */
    public function has(string $value): bool
    {
        return isset($this->values[$value]);
    }

    /**
     * @return \ArrayIterator<string, int>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->values);
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->has($offset);
    }

    public function offsetGet(mixed $offset): int
    {
        return $this->values[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \LogicException('You cannot set an offset');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new \LogicException('You cannot unset an offset');
    }

    public function count(): int
    {
        return count($this->values);
    }

    public function isEmpty(): bool
    {
        return [] === $this->values;
    }
}
