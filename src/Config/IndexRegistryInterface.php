<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Config;

/**
 * @extends \Traversable<Index>
 */
interface IndexRegistryInterface extends \Traversable
{
    /**
     * @throws \InvalidArgumentException if an index with the same name already exists
     */
    public function add(Index $index): void;

    /**
     * @throws \InvalidArgumentException if no index exists with the given name
     */
    public function get(string $name): Index;

    /**
     * This method returns the index where the $class is configured
     *
     * @param object|class-string $entity
     *
     * @return list<Index>
     */
    public function getByEntity(object|string $entity): array;
}
