<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Config;

/**
 * @extends \Traversable<Index>
 */
interface IndexRegistryInterface extends \Traversable, \Countable
{
    public function has(string $name): bool;

    /**
     * @throws \InvalidArgumentException if an index with the same name already exists
     */
    public function add(Index $index): void;

    /**
     * @throws \InvalidArgumentException if no index exists with the given name
     */
    public function get(string $name): Index;

    /**
     * This method returns the indexes where the $class is configured
     *
     * @param object|class-string $entity
     *
     * @return array<string, Index>
     */
    public function getByEntity(object|string $entity): array;

    /**
     * Returns all indexes, indexed by their name
     *
     * @return array<string, Index>
     */
    public function getAll(): array;

    /**
     * Returns a list of all index names
     *
     * @return list<string>
     */
    public function getNames(): array;
}
