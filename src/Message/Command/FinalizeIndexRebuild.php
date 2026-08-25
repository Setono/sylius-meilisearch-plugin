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
    ) {
        if ($index instanceof IndexConfig) {
            $index = $index->name;
        }

        Assert::stringNotEmpty($index);
        Assert::notEmpty($liveUids);
        Assert::allStringNotEmpty($liveUids);
        Assert::stringNotEmpty($rebuildId);

        $this->index = $index;
    }
}
