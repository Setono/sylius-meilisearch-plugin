<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Meilisearch;

use Meilisearch\Client;
use Meilisearch\Contracts\TasksQuery;

final class IndexSwapTasks
{
    private function __construct()
    {
    }

    /**
     * Returns the number of enqueued or processing indexSwap tasks involving any of the given index
     * uids. A swap task has a null indexUid, so the tasks API cannot filter it by index (and its
     * "total" even counts such tasks as if an indexUids filter matched) — the matching has to happen
     * client side, on the swaps listed in the task details.
     *
     * @param list<string> $uids
     */
    public static function pending(Client $client, array $uids): int
    {
        $query = new TasksQuery();
        $query->setTypes(['indexSwap']);
        $query->setStatuses(['enqueued', 'processing']);
        $query->setLimit(100);

        $pending = 0;

        foreach ($client->getTasks($query)->getResults() as $task) {
            if (self::touches($task, $uids)) {
                ++$pending;
            }
        }

        return $pending;
    }

    /**
     * @param array<array-key, mixed> $task
     * @param list<string> $uids
     */
    private static function touches(array $task, array $uids): bool
    {
        $details = $task['details'] ?? null;
        if (!is_array($details)) {
            return false;
        }

        $swaps = $details['swaps'] ?? null;
        if (!is_array($swaps)) {
            return false;
        }

        foreach ($swaps as $swap) {
            if (!is_array($swap)) {
                continue;
            }

            $indexes = $swap['indexes'] ?? null;
            if (!is_array($indexes)) {
                continue;
            }

            foreach ($indexes as $indexUid) {
                if (in_array($indexUid, $uids, true)) {
                    return true;
                }
            }
        }

        return false;
    }
}
