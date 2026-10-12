<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Indexer;

use Setono\SyliusMeilisearchPlugin\Resolver\IndexUid\RebuildUid;
use Webmozart\Assert\Assert;

/**
 * One run of the full-rebuild protocol driven by the Index message: its id (see RebuildUid) and the
 * live index uids it rebuilds.
 *
 * The live uids are resolved when the run starts and frozen here: the batches and the finalization
 * must agree on them even if the index scopes change mid-rebuild (e.g. a channel is enabled). The swap
 * is all-or-nothing, so a pair whose rebuild index was never created would fail the entire swap.
 */
final class Rebuild
{
    public function __construct(
        public readonly string $id,
        /** @var non-empty-list<string> $liveUids */
        public readonly array $liveUids,
    ) {
        Assert::stringNotEmpty($id);
        Assert::notEmpty($liveUids);
        Assert::allStringNotEmpty($liveUids);
    }

    public function uidOf(string $liveUid): string
    {
        return RebuildUid::from($liveUid, $this->id);
    }

    /**
     * @return non-empty-list<string>
     */
    public function uids(): array
    {
        return array_map($this->uidOf(...), $this->liveUids);
    }

    /**
     * Every batch of the run records itself in this index once it has handed all its documents to
     * Meilisearch. The finalization counts the recorded batches to know when the rebuild is complete.
     */
    public function ledgerUid(): string
    {
        return RebuildUid::ledger($this->liveUids[0], $this->id);
    }

    public function covers(string $liveUid): bool
    {
        return in_array($liveUid, $this->liveUids, true);
    }
}
