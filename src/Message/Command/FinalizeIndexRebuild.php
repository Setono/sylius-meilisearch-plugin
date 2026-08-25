<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Message\Command;

use Setono\SyliusMeilisearchPlugin\Config\Index as IndexConfig;
use Webmozart\Assert\Assert;

/**
 * Finalizes a full rebuild by atomically swapping each rebuild index with its live counterpart
 * and deleting the (then old) rebuild index. It must be dispatched on the same bus as the
 * IndexEntities batches, after all of them, so that it is handled only when every batch has
 * been pushed to Meilisearch.
 */
final class FinalizeIndexRebuild implements CommandInterface
{
    /**
     * This is the index being rebuilt
     */
    public readonly string $index;

    public function __construct(
        IndexConfig|string $index,

        /**
         * The live index uids are resolved when the rebuild starts and frozen here: resolving them
         * again when this message is handled could produce a different set (e.g. a channel enabled
         * mid-rebuild), and because the swap is all-or-nothing, a pair whose rebuild index was never
         * created would fail the entire swap.
         *
         * @var list<string> $liveUids
         */
        public readonly array $liveUids,

        /**
         * The id of the rebuild run to finalize (see RebuildUid) — each run builds into its own
         * rebuild indexes, so this message only ever swaps and deletes its own generation
         */
        public readonly string $rebuildId,

        /**
         * When the rebuild started. Used to compute the observed indexing throughput, which in turn
         * decides how long to wait before checking the rebuild's completeness again.
         */
        public readonly \DateTimeImmutable $startedAt,

        /**
         * How many document-addition tasks the rebuild must have enqueued on its rebuild indexes
         * before it is complete (dispatched batches × index scopes). The swap must only happen once
         * they have all arrived: message transports do not guarantee ordering under retries, so a
         * transiently failed batch can be redelivered after this message.
         */
        public readonly int $expectedTasks,

        /**
         * How many of the expected tasks had arrived at the previous completeness check
         */
        public readonly int $completedTasks = 0,

        /**
         * How many consecutive completeness checks have seen no new tasks arrive
         */
        public readonly int $stagnantChecks = 0,
    ) {
        if ($index instanceof IndexConfig) {
            $index = $index->name;
        }

        Assert::stringNotEmpty($index);
        Assert::notEmpty($liveUids);
        Assert::allStringNotEmpty($liveUids);
        Assert::stringNotEmpty($rebuildId);
        Assert::greaterThanEq($expectedTasks, 0);
        Assert::greaterThanEq($completedTasks, 0);
        Assert::greaterThanEq($stagnantChecks, 0);

        $this->index = $index;
    }
}
