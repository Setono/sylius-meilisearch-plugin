<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Meilisearch;

use Meilisearch\Client;
use Meilisearch\Contracts\IndexesQuery;
use Setono\SyliusMeilisearchPlugin\Resolver\IndexUid\RebuildUid;

final class RebuildIndexes
{
    private const PAGE_SIZE = 200;

    private function __construct()
    {
    }

    /**
     * Returns the uid of every index on the instance
     *
     * @return list<string>
     */
    public static function all(Client $client): array
    {
        $uids = [];
        $offset = 0;

        do {
            $results = $client->getIndexes((new IndexesQuery())->setOffset($offset)->setLimit(self::PAGE_SIZE));

            foreach ($results->getResults() as $index) {
                $uid = $index->getUid();
                if (null !== $uid) {
                    $uids[] = $uid;
                }
            }

            $offset += self::PAGE_SIZE;
        } while ($offset < $results->getTotal());

        return $uids;
    }

    /**
     * Returns the rebuild indexes of the given live indexes that a rebuild in flight may still swap
     * live, indexed by live uid. A change written only to a live index is reverted by such a swap, so
     * incremental writes are mirrored into these indexes. Stale rebuild indexes belong to abandoned
     * runs and are left out, and so are the ledger indexes, which hold no documents.
     *
     * @param list<string> $liveUids
     *
     * @return array<string, list<string>>
     */
    public static function inFlight(Client $client, array $liveUids): array
    {
        if ([] === $liveUids) {
            return [];
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $inFlight = [];

        foreach (self::all($client) as $uid) {
            if (RebuildUid::isLedger($uid)) {
                continue;
            }

            foreach ($liveUids as $liveUid) {
                if (RebuildUid::isRebuildOf($uid, $liveUid) && !RebuildUid::isStale($uid, $liveUid, $now)) {
                    $inFlight[$liveUid][] = $uid;

                    break;
                }
            }
        }

        return $inFlight;
    }
}
