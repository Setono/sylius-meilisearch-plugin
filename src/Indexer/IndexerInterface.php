<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Indexer;

use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;

interface IndexerInterface
{
    /**
     * Will index _all_ entities on the associated index by writing them to the rebuild indexes of
     * the given rebuild run (see \Setono\SyliusMeilisearchPlugin\Resolver\IndexUid\RebuildUid).
     * This is one step of the full-rebuild protocol driven by the Index message: the rebuild
     * indexes are prepared under the same id before this method is called, and the rebuild is
     * finalized — atomically swapped with the live indexes — by the FinalizeIndexRebuild message
     * dispatched afterwards.
     */
    public function index(string $rebuildId): void;

    /**
     * Will index a single entity
     */
    public function indexEntity(IndexableInterface $entity): void;

    /**
     * @param array<array-key, IndexableInterface> $entities
     * @param string|null $rebuildId when set, the documents are written to the rebuild indexes of
     *                               the rebuild run with this id instead of the live indexes
     */
    public function indexEntities(array $entities, ?string $rebuildId = null): void;

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
