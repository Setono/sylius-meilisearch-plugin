<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Indexer;

use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\Config\IndexableResource;
use Setono\SyliusMeilisearchPlugin\Exception\NonExistingIndexException;
use Setono\SyliusMeilisearchPlugin\Exception\NonExistingResourceException;
use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
use Sylius\Component\Resource\Model\ResourceInterface;

interface IndexerInterface
{
    /**
     * Will index _all_ resources on the given index
     *
     * @throws NonExistingIndexException if the $index is a string and the index doesn't exist
     */
    public function index(Index|string $index): void;

    /**
     * This method will index all entities for a given indexable resource on the given index
     *
     * @throws NonExistingIndexException if the $index is a string and the index doesn't exist
     * @throws NonExistingResourceException if the $resource doesn't exist on the given $index
     */
    public function indexResource(Index|string $index, string $resource): void;

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
