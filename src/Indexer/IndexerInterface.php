<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Indexer;

use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;

interface IndexerInterface
{
    /**
     * Will index _all_ entities on the associated index
     *
     * When $rebuild is true, the documents are written to the rebuild index of each scope (see
     * \Setono\SyliusMeilisearchPlugin\Resolver\IndexUid\RebuildUid) instead of the live index.
     * The caller is responsible for finalizing the rebuild — i.e. atomically swapping the rebuild
     * indexes with the live indexes — by dispatching the FinalizeIndexRebuild message afterwards.
     */
    public function index(bool $rebuild = false): void;

    /**
     * Will index a single entity
     */
    public function indexEntity(IndexableInterface $entity): void;

    /**
     * @param array<array-key, IndexableInterface> $entities
     */
    public function indexEntities(array $entities, bool $rebuild = false): void;

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
