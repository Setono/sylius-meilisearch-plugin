<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Message\Handler;

use Meilisearch\Client;
use Meilisearch\Contracts\TasksQuery;
use Meilisearch\Exceptions\ApiException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Setono\SyliusMeilisearchPlugin\Indexer\Rebuild;
use Setono\SyliusMeilisearchPlugin\Meilisearch\IndexSwapTasks;
use Setono\SyliusMeilisearchPlugin\Meilisearch\Task;
use Setono\SyliusMeilisearchPlugin\Message\Command\FinalizeIndexRebuild;
use Setono\SyliusMeilisearchPlugin\Resolver\IndexUid\RebuildUid;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

/**
 * Every check is safe to repeat — a redelivered, retried or replayed message included — because the
 * handler first looks for the swap of its own run in the task history and never swaps twice. Errors
 * are therefore left to the transport's retry strategy.
 */
final class FinalizeIndexRebuildHandler
{
    /**
     * Bounds for the delay before the next completeness check (in seconds). While batches keep being
     * recorded, the delay is derived from the observed throughput: elapsed time and recorded batches
     * give a rate, and the remaining batches divided by that rate estimate how long the rest of the
     * rebuild needs.
     */
    private const MIN_DELAY = 5;

    private const MAX_DELAY = 300;

    /**
     * Without progress to extrapolate from (the workers have not reached the batches yet, or
     * Meilisearch is still processing the run's tasks) the delay doubles from MIN_DELAY up to this
     */
    private const MAX_IDLE_DELAY = 60;

    /**
     * Give up when no new batch has been recorded for this long although some have been: the batches
     * are dispatched together, so once the workers have reached them the rest follow — unless they
     * are lost (e.g. exhausted their retries into the failure transport), and an incomplete rebuild
     * must never be swapped live. Comfortably longer than the default redelivery timeout of the
     * Doctrine and Redis transports (one hour), after which a batch of a crashed worker is retried.
     */
    private const STAGNATION_TIMEOUT = '-2 hours';

    /**
     * How far back to look for swap tasks before the rebuild started, to tolerate a clock skew
     * between the application and Meilisearch
     */
    private const CLOCK_SKEW_MARGIN = '-1 hour';

    /**
     * Whether the handler is currently handling a message. See __invoke().
     */
    private bool $handling = false;

    private bool $handedBack = false;

    public function __construct(
        private readonly Client $client,
        private readonly MessageBusInterface $commandBus,
        private readonly ClockInterface $clock = new NativeClock(),
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function __invoke(FinalizeIndexRebuild $message): void
    {
        // On a synchronous bus (the default without transport routing) the delay stamp is ignored and
        // a rescheduled check is handled immediately, inside the invocation that rescheduled it.
        // Recursing for every check would grow the stack until Meilisearch has caught up, so the
        // nested invocation hands the check back, and the outer one waits and loops instead.
        if ($this->handling) {
            $this->handedBack = true;

            return;
        }

        $this->handling = true;

        try {
            while (null !== $next = $this->check($message)) {
                [$message, $delay] = $next;

                $this->commandBus->dispatch($message, [new DelayStamp($delay * 1000)]);

                // Sent to a transport: a worker handles the next check once the delay has passed
                if (!$this->takeHandedBack()) {
                    return;
                }

                $this->clock->sleep($delay);
            }
        } finally {
            $this->handling = false;
            $this->handedBack = false;
        }
    }

    /**
     * Whether a nested invocation handed back the check dispatched last (see __invoke())
     */
    private function takeHandedBack(): bool
    {
        $handedBack = $this->handedBack;
        $this->handedBack = false;

        return $handedBack;
    }

    /**
     * @return array{FinalizeIndexRebuild, int}|null the next check and the delay before it in seconds,
     *                                               or null when the rebuild is finished one way or the other
     */
    private function check(FinalizeIndexRebuild $message): ?array
    {
        $rebuild = $message->rebuild;
        $now = $this->clock->now();

        [$swapped, $superseded] = $this->findSwaps($message);

        if ($swapped) {
            // This finalization has run before (a redelivery, a retry after an error, or a replay
            // from the failure transport). Swapping again would put the previous data back live, so
            // only make sure the leftovers are deleted.
            $this->deleteRebuildIndexes($rebuild);

            $this->logger->info('The rebuild of the "{index}" index has already been swapped live', [
                'index' => $message->index,
                'rebuildId' => $rebuild->id,
            ]);

            return null;
        }

        if ($superseded) {
            // A rebuild that started later has already been swapped live: swapping this one now would
            // replace its fresher documents and settings with older ones
            $this->deleteRebuildIndexes($rebuild);

            $this->logger->info('The rebuild of the "{index}" index was superseded by a later rebuild that has already been swapped live', [
                'index' => $message->index,
                'rebuildId' => $rebuild->id,
            ]);

            return null;
        }

        // Aligned with the staleness window after which a later rebuild deletes this run's indexes
        if (RebuildUid::isStaleId($rebuild->id, $now)) {
            $this->abandon($message, 'the rebuild has not completed within 24 hours');

            return null;
        }

        $recorded = $this->countRecordedBatches($rebuild);
        $progressed = $recorded > $message->recorded;
        $progressedAt = $progressed ? $now : ($message->progressedAt ?? $message->startedAt);

        if ($recorded < $message->batches) {
            if (!$progressed && $recorded > 0 && $progressedAt <= $now->modify(self::STAGNATION_TIMEOUT)) {
                $this->abandon($message, sprintf(
                    'only %d of %d batches were indexed, and none for two hours. Check the failure transport for lost batches',
                    $recorded,
                    $message->batches,
                ));

                return null;
            }

            $this->logger->info('The rebuild of the "{index}" index is not complete yet ({recorded} of {batches} batches indexed)', [
                'index' => $message->index,
                'rebuildId' => $rebuild->id,
                'recorded' => $recorded,
                'batches' => $message->batches,
            ]);

            return $this->next($message, $recorded, $progressed, $progressedAt, $now);
        }

        // Every batch has handed its documents to Meilisearch, but the swap must also wait until
        // Meilisearch has processed them: only then is it known that none of them failed. Tasks
        // enqueued after this point (incremental changes mirrored into the rebuild indexes) are not
        // waited for — Meilisearch processes them before the swap anyway, as it executes tasks in
        // enqueue order.
        $newestTaskUid = $this->getNewestTaskUid();

        if ([] !== $this->getTasks($rebuild, ['enqueued', 'processing'], $newestTaskUid)) {
            return $this->next($message, $recorded, $progressed, $progressedAt, $now);
        }

        $failed = $this->getTasks($rebuild, ['failed'], $newestTaskUid);
        if ([] !== $failed) {
            $this->abandon($message, sprintf(
                'Meilisearch failed to process a task of the rebuild (task %d: %s)',
                $failed[0]->uid,
                $failed[0]->error['message'] ?? 'unknown error',
            ));

            return null;
        }

        $swaps = [];

        foreach ($rebuild->liveUids as $liveUid) {
            // The swap fails as a whole if either side of a pair is missing, and on the very first
            // rebuild the live index does not exist yet. Meilisearch executes tasks in enqueue
            // order, so the creation is guaranteed to have happened when the swap is processed.
            if (!$this->indexExists($liveUid)) {
                $this->client->createIndex($liveUid);
            }

            $swaps[] = [$liveUid, $rebuild->uidOf($liveUid)];
        }

        $this->client->swapIndexes($swaps);

        // Processed after the swap, i.e. when the rebuild uids hold the previous live data
        $this->deleteRebuildIndexes($rebuild);

        $this->logger->info('Finalized the rebuild of the "{index}" index: the rebuild indexes have been swapped with the live indexes', [
            'index' => $message->index,
            'liveUids' => $rebuild->liveUids,
            'rebuildId' => $rebuild->id,
        ]);

        return null;
    }

    /**
     * Looks through the swaps enqueued since the rebuild started for one of this run (it has been
     * swapped already) or of a run that started later (it has been superseded). A processed swap
     * renames the swapped indexes throughout the task history, including in the details of earlier
     * swaps — but a rebuild uid only ever appears in the swap history once its own run has swapped.
     *
     * Meilisearch has no conditional swap, so two runs passing this check at the very same moment can
     * still swap out of order. That needs both to finish within milliseconds of each other, and the
     * next rebuild corrects it.
     *
     * @return array{bool, bool}
     */
    private function findSwaps(FinalizeIndexRebuild $message): array
    {
        $rebuild = $message->rebuild;
        $ownUids = $rebuild->uids();

        $swapped = false;
        $superseded = false;

        $swaps = IndexSwapTasks::find(
            $this->client,
            ['enqueued', 'processing', 'succeeded'],
            $message->startedAt->modify(self::CLOCK_SKEW_MARGIN),
        );

        foreach ($swaps as $swap) {
            foreach (IndexSwapTasks::uids($swap) as $uid) {
                if (in_array($uid, $ownUids, true)) {
                    $swapped = true;

                    continue;
                }

                foreach ($rebuild->liveUids as $liveUid) {
                    $id = RebuildUid::idOf($uid, $liveUid);
                    if (null !== $id && strcmp($id, $rebuild->id) > 0) {
                        $superseded = true;
                    }
                }
            }
        }

        return [$swapped, $superseded];
    }

    private function countRecordedBatches(Rebuild $rebuild): int
    {
        try {
            $stats = $this->client->index($rebuild->ledgerUid())->stats();
        } catch (ApiException $e) {
            // The ledger index is created when the first batch records itself
            if (404 === $e->httpStatus) {
                return 0;
            }

            throw $e;
        }

        $documents = $stats['numberOfDocuments'] ?? 0;

        return is_int($documents) ? $documents : 0;
    }

    private function getNewestTaskUid(): ?int
    {
        $query = new TasksQuery();
        $query->setLimit(1);

        $task = $this->client->getTasks($query)->getResults()[0] ?? null;

        return is_array($task) ? Task::fromArray($task)->uid : null;
    }

    /**
     * Returns (up to one of) the run's tasks with the given statuses on its rebuild indexes
     *
     * @param list<string> $statuses
     *
     * @return list<Task>
     */
    private function getTasks(Rebuild $rebuild, array $statuses, ?int $from): array
    {
        $query = new TasksQuery();
        $query->setIndexUids($rebuild->uids());
        // The types also keep swap tasks out, which the indexUids filter cannot handle (see IndexSwapTasks)
        $query->setTypes(['settingsUpdate', 'documentAdditionOrUpdate', 'documentDeletion']);
        $query->setStatuses($statuses);
        $query->setLimit(1);

        if (null !== $from) {
            $query->setFrom($from);
        }

        return array_map(Task::fromArray(...), array_values($this->client->getTasks($query)->getResults()));
    }

    /**
     * @return array{FinalizeIndexRebuild, int}
     */
    private function next(
        FinalizeIndexRebuild $message,
        int $recorded,
        bool $progressed,
        \DateTimeImmutable $progressedAt,
        \DateTimeImmutable $now,
    ): array {
        $idleChecks = $progressed ? 0 : $message->idleChecks + 1;

        if ($progressed) {
            $elapsed = max(1, $now->getTimestamp() - $message->startedAt->getTimestamp());
            $estimate = (int) ceil(($message->batches - $recorded) / ($recorded / $elapsed));

            $delay = max(self::MIN_DELAY, min(self::MAX_DELAY, $estimate));
        } else {
            $delay = min(self::MAX_IDLE_DELAY, self::MIN_DELAY * 2 ** min($idleChecks - 1, 4));
        }

        return [
            new FinalizeIndexRebuild(
                $message->index,
                $message->rebuild,
                $message->startedAt,
                $message->batches,
                $recorded,
                $progressedAt,
                $idleChecks,
            ),
            $delay,
        ];
    }

    private function abandon(FinalizeIndexRebuild $message, string $reason): void
    {
        $this->logger->error('Giving up on the rebuild of the "{index}" index: {reason}. The live indexes keep serving their current documents.', [
            'index' => $message->index,
            'rebuildId' => $message->rebuild->id,
            'reason' => $reason,
        ]);

        $this->deleteRebuildIndexes($message->rebuild);
    }

    private function deleteRebuildIndexes(Rebuild $rebuild): void
    {
        foreach ($rebuild->uids() as $uid) {
            $this->client->deleteIndex($uid);
        }

        $this->client->deleteIndex($rebuild->ledgerUid());
    }

    private function indexExists(string $uid): bool
    {
        try {
            $this->client->getRawIndex($uid);
        } catch (ApiException $e) {
            if (404 === $e->httpStatus) {
                return false;
            }

            throw $e;
        }

        return true;
    }
}
