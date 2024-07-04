<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Config;

/**
 * @implements \IteratorAggregate<string, Index>
 */
final class IndexRegistry implements \IteratorAggregate, IndexRegistryInterface
{
    public function has(string $name): bool
    {
        return isset($this->indexes[$name]);
    }

    /**
     * An array of indexes, indexed by the name of the index
     *
     * @var array<string, Index>
     */
    private array $indexes = [];

    public function add(Index $index): void
    {
        if (isset($this->indexes[$index->name])) {
            throw new \InvalidArgumentException(sprintf('An index with the name %s already exists', $index->name));
        }

        $this->indexes[$index->name] = $index;
    }

    public function get(string $name): Index
    {
        if (!isset($this->indexes[$name])) {
            throw new \InvalidArgumentException(sprintf(
                'No index exists with the name %s. Available indexes are: [%s]',
                implode(', ', array_keys($this->indexes)),
                $name,
            ));
        }

        return $this->indexes[$name];
    }

    public function getByEntity(object|string $entity): array
    {
        $entity = is_object($entity) ? $entity::class : $entity;

        $indexes = [];

        foreach ($this->indexes as $name => $index) {
            if ($index->hasEntity($entity)) {
                $indexes[$name] = $index;
            }
        }

        return $indexes;
    }

    public function getAll(): array
    {
        return $this->indexes;
    }

    public function getNames(): array
    {
        return array_keys($this->indexes);
    }

    /**
     * @return \ArrayIterator<string, Index>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->indexes);
    }

    public function count(): int
    {
        return count($this->indexes);
    }
}
