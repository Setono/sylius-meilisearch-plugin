<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Meilisearch;

use Meilisearch\Client;
use Meilisearch\Contracts\TasksQuery;

final class IndexSwapTasks
{
    private const PAGE_SIZE = 100;

    private function __construct()
    {
    }

    /**
     * Returns the indexSwap tasks with the given statuses that were enqueued after the given time. A
     * swap task has a null indexUid, so the tasks API cannot filter it by index (and its "total" even
     * counts such tasks as if an indexUids filter matched) — callers match on the uids the swap
     * involves instead (see IndexSwapTasks::uids()).
     *
     * @param list<string> $statuses
     *
     * @return list<Task>
     */
    public static function find(Client $client, array $statuses, \DateTimeImmutable $enqueuedAfter): array
    {
        $query = new TasksQuery();
        $query->setTypes(['indexSwap']);
        $query->setStatuses($statuses);
        $query->setAfterEnqueuedAt($enqueuedAfter);
        $query->setLimit(self::PAGE_SIZE);

        $tasks = [];

        do {
            $results = $client->getTasks($query);

            foreach ($results->getResults() as $task) {
                $tasks[] = Task::fromArray($task);
            }

            // The SDK maps the API's null "next" (no more pages) to 0
            $next = $results->getNext();
            $query->setFrom($next);
        } while ($next > 0);

        return $tasks;
    }

    /**
     * Returns every index uid the swap task involves. Note that processing a swap renames the swapped
     * indexes throughout the task history, including in the details of earlier swap tasks.
     *
     * @return list<string>
     */
    public static function uids(Task $task): array
    {
        $swaps = $task->details['swaps'] ?? null;
        if (!is_array($swaps)) {
            return [];
        }

        $uids = [];

        foreach ($swaps as $swap) {
            $indexes = is_array($swap) ? ($swap['indexes'] ?? null) : null;
            if (!is_array($indexes)) {
                continue;
            }

            foreach ($indexes as $uid) {
                if (is_string($uid)) {
                    $uids[] = $uid;
                }
            }
        }

        return $uids;
    }
}
