<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Indexer;

use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;

interface IndexerInterface
{
    /**
     * Will index _all_ entities on the associated index by writing them to the rebuild indexes of
     * the given rebuild run. This is one step of the full-rebuild protocol driven by the Index
     * message: the rebuild indexes are prepared before this method is called, and the rebuild is
     * finalized — atomically swapped with the live indexes — by the FinalizeIndexRebuild message
     * dispatched afterwards.
     *
     * Returns the number of dispatched batches, numbered from 1. The finalization swaps only once
     * every batch number is recorded in the run's ledger (see Rebuild::ledgerUid()), which the
     * IndexEntities handler does after a batch has been indexed.
     */
    public function index(Rebuild $rebuild): int;

    /**
     * Will index a single entity
     */
    public function indexEntity(IndexableInterface $entity): void;

    /**
     * Outside a rebuild, the documents are written to the live indexes and mirrored into the rebuild
     * indexes of any rebuild in flight (see RebuildIndexes::inFlight()), whose swap would otherwise
     * revert the change.
     *
     * @param array<array-key, IndexableInterface> $entities
     * @param Rebuild|null $rebuild when set, the documents are written to the rebuild indexes of this
     *                              rebuild run instead
     */
    public function indexEntities(array $entities, ?Rebuild $rebuild = null): void;

    public function removeEntity(IndexableInterface $entity): void;

    /**
     * @param array<array-key, IndexableInterface> $entities
     */
    public function removeEntities(array $entities): void;

    /**
     * Removes the documents with the given identifiers from every scope of the associated index, and
     * from the rebuild indexes of any rebuild in flight.
     *
     * This is what makes deletion work: when an entity is removed, the row is already gone from the
     * database, so the only thing left to go on is the document identifier (the stringified id).
     *
     * @param list<string|int> $documentIds
     */
    public function removeDocuments(array $documentIds): void;
}
