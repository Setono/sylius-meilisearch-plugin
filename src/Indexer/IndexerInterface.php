<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Indexer;

use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\Exception\NonExistingIndexException;
use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;

interface IndexerInterface
{
    /**
     * Will index _all_ resources on the given index
     *
     * @throws NonExistingIndexException if the $index is a string and the index doesn't exist
     */
    public function index(Index|string $index): void;

    /**
     * Will index a single entity
     */
    public function indexEntity(IndexableInterface $entity): void;

    /**
     * This method will index an entity with the given $id of the given $type
     *
     * @param class-string<IndexableInterface> $type
     */
    public function indexEntityWithId(mixed $id, string $type): void;

    /**
     * @param list<IndexableInterface> $entities
     */
    public function indexEntities(array $entities): void;

    /**
     * This method will index all the entities matching the $ids of the given $type
     *
     * @param list<mixed> $ids
     * @param class-string<IndexableInterface> $type
     */
    public function indexEntitiesWithIds(array $ids, string $type): void;

    public function removeEntity(IndexableInterface $entity): void;

    /**
     * This method will remove an entity with the given $id of the given $type
     *
     * @param class-string<IndexableInterface> $type
     */
    public function removeEntityWithId(mixed $id, string $type): void;

    /**
     * @param list<IndexableInterface> $entities
     */
    public function removeEntities(array $entities): void;

    /**
     * This method will remove all the entities matching the $ids of the given $type
     *
     * @param list<mixed> $ids
     * @param class-string<IndexableInterface> $type
     */
    public function removeEntitiesWithIds(array $ids, string $type): void;
}
