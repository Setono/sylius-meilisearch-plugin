<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Indexer;

use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;

interface IndexerInterface
{
    /**
     * Will index _all_ entities on the associated index
     */
    public function index(): void;

    /**
     * Will index a single entity
     */
    public function indexEntity(IndexableInterface $entity): void;

    /**
     * @param array<array-key, IndexableInterface> $entities
     */
    public function indexEntities(array $entities): void;

    public function removeEntity(IndexableInterface $entity): void;

    /**
     * @param array<array-key, IndexableInterface> $entities
     */
    public function removeEntities(array $entities): void;

    /**
     * Removes the documents with the given identifiers from every scope of the associated index.
     *
     * This is what makes deletion work: when an entity is removed, the row is already gone from the
     * database, so the only thing left to go on is the document identifier (the stringified id).
     *
     * @param list<string|int> $documentIds
     */
    public function removeDocuments(array $documentIds): void;
}
