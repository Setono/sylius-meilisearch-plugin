<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Config;

use Setono\SyliusMeilisearchPlugin\Exception\NonExistingIndexException;

/**
 * @implements \IteratorAggregate<string, Index>
 */
final class IndexRegistry implements \IteratorAggregate, IndexRegistryInterface
{
    /**
     * An array of indexes, indexed by the name of the index
     *
     * @var array<string, Index>
     */
    private array $indexes = [];

    public function add(Index $index): void
    {
        if (isset($this->indexes[$index->name])) {
            throw new \InvalidArgumentException(sprintf('An index with the name %s already exists', $index->name)); // todo better exception
        }

        $this->indexes[$index->name] = $index;
    }

    /**
     * @throws NonExistingIndexException if no index exists with the given name
     */
    public function get(string $name): Index
    {
        if (!isset($this->indexes[$name])) {
            throw NonExistingIndexException::fromName($name, array_keys($this->indexes));
        }

        return $this->indexes[$name];
    }

    public function getByEntity(object|string $entity): array
    {
        $entity = is_object($entity) ? $entity::class : $entity;

        $indexes = [];

        foreach ($this->indexes as $index) {
            if ($index->hasEntity($entity)) {
                $indexes[] = $index;
            }
        }

        return $indexes;
    }

    /**
     * @return \ArrayIterator<string, Index>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->indexes);
    }
}
