<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Message\Handler;

use Meilisearch\Client;
use Meilisearch\Contracts\TasksQuery;
use Meilisearch\Exceptions\ApiException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Setono\SyliusMeilisearchPlugin\Meilisearch\IndexSwapTasks;
use Setono\SyliusMeilisearchPlugin\Message\Command\FinalizeIndexRebuild;
use Setono\SyliusMeilisearchPlugin\Resolver\IndexUid\RebuildUid;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

final class FinalizeIndexRebuildHandler
{
    /**
     * Bounds for the delay before the next completeness check (in seconds). The delay itself is
     * derived from the observed throughput: elapsed time and arrived tasks give a rate, and the
     * remaining tasks divided by that rate estimate how long the rest of the rebuild needs.
     */
    private const MIN_DELAY = 5;

    private const MAX_DELAY = 300;

    /**
     * Give up after this many consecutive completeness checks without a single new task arriving —
     * at that point the missing batches are lost (e.g. exhausted their retries into the failure
     * transport) and the rebuild must not go live incomplete. This also bounds the recursion on a
     * synchronous bus, where a delay stamp is ignored and a rescheduled message is handled inline.
     */
    private const MAX_STAGNANT_CHECKS = 10;

    public function __construct(
        private readonly Client $client,
        private readonly MessageBusInterface $commandBus,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function __invoke(FinalizeIndexRebuild $message): void
    {
        try {
            // A redelivery of this message must not enqueue a second swap: the first swap already
            // moved the fresh data into the live index, so swapping again would move it back out
            if (IndexSwapTasks::pending($this->client, $message->liveUids) > 0) {
                $this->logger->warning('An index swap is already enqueued or processing for the "{index}" index, skipping this finalization', [
                    'index' => $message->index,
                    'liveUids' => $message->liveUids,
                ]);

                return;
            }

            $arrived = $this->countArrivedTasks($message);

            if ($arrived < $message->expectedTasks) {
                $this->reschedule($message, $arrived);

                return;
            }

            $swaps = [];

            foreach ($message->liveUids as $liveUid) {
                // The swap fails as a whole if either side of a pair is missing, and on the very first
                // rebuild the live index does not exist yet. Meilisearch executes tasks in enqueue
                // order, so the creation is guaranteed to have happened when the swap is processed.
                if (!$this->indexExists($liveUid)) {
                    $this->client->createIndex($liveUid);
                }

                $swaps[] = [$liveUid, RebuildUid::from($liveUid, $message->rebuildId)];
            }

            $this->client->swapIndexes($swaps);

            foreach ($message->liveUids as $liveUid) {
                // After the swap the rebuild uid holds the previous live data
                $this->client->deleteIndex(RebuildUid::from($liveUid, $message->rebuildId));
            }
        } catch (\Throwable $e) {
            // Retrying is unsafe because swapIndexes is not idempotent: a retry after a partial
            // failure could swap the stale data back into the live index. Failing here leaves the
            // live index serving its current data, and the orphaned rebuild index is deleted when
            // a later rebuild starts.
            throw new UnrecoverableMessageHandlingException(message: $e->getMessage(), previous: $e);
        }

        $this->logger->info('Finalized the rebuild of the "{index}" index: the rebuild indexes have been swapped with the live indexes', [
            'index' => $message->index,
            'liveUids' => $message->liveUids,
            'rebuildId' => $message->rebuildId,
        ]);
    }

    /**
     * The swap may only be enqueued once every batch has demonstrably reached Meilisearch: message
     * transports do not guarantee ordering — a transiently failed batch is retried later, behind
     * this message — and Meilisearch executes tasks in enqueue order, so a batch arriving after
     * the swap would be lost with the rebuild indexes. Every batch creates exactly one
     * document-addition task per scope (see IndexerInterface::index()), and the rebuild uids are
     * unique to this run, so the task count is an exact ledger of the run's progress.
     */
    private function countArrivedTasks(FinalizeIndexRebuild $message): int
    {
        $query = new TasksQuery();
        $query->setTypes(['documentAdditionOrUpdate']);
        $query->setIndexUids(array_map(
            static fn (string $liveUid): string => RebuildUid::from($liveUid, $message->rebuildId),
            $message->liveUids,
        ));
        $query->setLimit(1);

        return $this->client->getTasks($query)->getTotal();
    }

    private function reschedule(FinalizeIndexRebuild $message, int $arrived): void
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $stagnantChecks = $arrived > $message->completedTasks ? 0 : $message->stagnantChecks + 1;

        // Aligned with the staleness window after which a later rebuild deletes this run's indexes
        $stale = $message->startedAt <= $now->modify(RebuildUid::STALE_AFTER);

        if ($stale || $stagnantChecks >= self::MAX_STAGNANT_CHECKS) {
            $this->logger->error('Giving up on finalizing the rebuild of the "{index}" index: only {arrived} of {expected} batch tasks arrived. The live indexes keep serving their current documents; check the failure transport for lost batches. The rebuild indexes will be deleted by a later rebuild once stale.', [
                'index' => $message->index,
                'rebuildId' => $message->rebuildId,
                'arrived' => $arrived,
                'expected' => $message->expectedTasks,
            ]);

            return;
        }

        $delay = $this->delay($message, $arrived, $now);

        $this->logger->info('The rebuild of the "{index}" index is not complete yet ({arrived} of {expected} batch tasks arrived), checking again in {delay} seconds', [
            'index' => $message->index,
            'rebuildId' => $message->rebuildId,
            'arrived' => $arrived,
            'expected' => $message->expectedTasks,
            'delay' => $delay,
        ]);

        $this->commandBus->dispatch(
            new FinalizeIndexRebuild(
                $message->index,
                $message->liveUids,
                $message->rebuildId,
                $message->startedAt,
                $message->expectedTasks,
                $arrived,
                $stagnantChecks,
            ),
            [new DelayStamp($delay * 1000)],
        );
    }

    private function delay(FinalizeIndexRebuild $message, int $arrived, \DateTimeImmutable $now): int
    {
        // With no throughput observed yet there is nothing to extrapolate from, and checking again
        // quickly would only burn through the stagnation allowance while workers are still starting
        if ($arrived < 1) {
            return self::MAX_DELAY;
        }

        $elapsed = max(1, $now->getTimestamp() - $message->startedAt->getTimestamp());
        $rate = $arrived / $elapsed;
        $estimate = (int) ceil(($message->expectedTasks - $arrived) / $rate);

        return max(self::MIN_DELAY, min(self::MAX_DELAY, $estimate));
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
