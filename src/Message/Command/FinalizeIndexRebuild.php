<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Message\Command;

use Setono\SyliusMeilisearchPlugin\Config\Index as IndexConfig;
use Setono\SyliusMeilisearchPlugin\Indexer\Rebuild;
use Webmozart\Assert\Assert;

/**
 * Finalizes a full rebuild by atomically swapping each rebuild index with its live counterpart and
 * deleting the (then old) rebuild indexes — once every batch of the run has been recorded in its
 * ledger and Meilisearch has processed all of the run's tasks successfully. Until then, the handler
 * reschedules this message, so it does not rely on being handled after the batches.
 */
final class FinalizeIndexRebuild implements CommandInterface
{
    /**
     * This is the index being rebuilt
     */
    public readonly string $index;

    public function __construct(
        IndexConfig|string $index,
        public readonly Rebuild $rebuild,

        /**
         * When the rebuild started. Used to compute the observed indexing throughput, which in turn
         * decides how long to wait before checking the rebuild's completeness again.
         */
        public readonly \DateTimeImmutable $startedAt,

        /**
         * How many batches the rebuild dispatched, i.e. how many it must have recorded in its ledger
         * before it is complete
         */
        public readonly int $batches,

        /**
         * How many batches had been recorded at the previous completeness check
         */
        public readonly int $recorded = 0,

        /**
         * When the number of recorded batches last increased
         */
        public readonly ?\DateTimeImmutable $progressedAt = null,

        /**
         * How many consecutive completeness checks have seen no new batches recorded
         */
        public readonly int $idleChecks = 0,
    ) {
        if ($index instanceof IndexConfig) {
            $index = $index->name;
        }

        Assert::stringNotEmpty($index);
        Assert::greaterThanEq($batches, 0);
        Assert::greaterThanEq($recorded, 0);
        Assert::greaterThanEq($idleChecks, 0);

        $this->index = $index;
    }
}
