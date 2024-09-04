<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Settings;

/**
 * @implements \ArrayAccess<int, string>
 * @implements \IteratorAggregate<int, string>
 */
final class UniqueList implements \JsonSerializable, \ArrayAccess, \IteratorAggregate, \Countable
{
    public function __construct(
        /** @var list<string> $items */
        private array $items = [],
        /**
         * If the list is empty, this is the value that will be returned when serializing to JSON
         *
         * @var list<string> $ifEmpty
         */
        private readonly array $ifEmpty = [],
    ) {
    }

    public function add(string ...$items): void
    {
        foreach ($items as $item) {
            if (in_array($item, $this->items, true)) {
                continue;
            }

            $this->items[] = $item;
        }
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->items[$offset]);
    }

    public function offsetGet(mixed $offset): string
    {
        return $this->items[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (null !== $offset) {
            throw new \LogicException('You cannot set an offset');
        }

        $this->add($value);
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->items[$offset]);

        $this->items = array_values($this->items);
    }

    /**
     * @return list<string>
     */
    public function jsonSerialize(): array
    {
        if ([] === $this->items) {
            return $this->ifEmpty;
        }

        return $this->items;
    }

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->items);
    }

    public function isEmpty(): bool
    {
        return [] === $this->items;
    }

    public function count(): int
    {
        return count($this->items);
    }
}
