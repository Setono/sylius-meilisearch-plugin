<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Settings;

/**
 * @template T of scalar
 *
 * @implements \ArrayAccess<int, T>
 */
final class UniqueList implements \JsonSerializable, \ArrayAccess
{
    /** @var list<T> */
    private array $items = [];

    /**
     * @param list<T> $default
     */
    public function __construct(private readonly array $default = [])
    {
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->items[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->items[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (null !== $offset) {
            throw new \LogicException('You cannot set an offset');
        }

        $this->items[] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->items[$offset]);
    }

    public function jsonSerialize(): array
    {
        if ([] === $this->items) {
            return $this->default;
        }

        return array_values(array_unique($this->items));
    }
}
