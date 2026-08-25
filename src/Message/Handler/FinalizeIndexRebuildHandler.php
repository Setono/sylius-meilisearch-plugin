<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Message\Handler;

use Meilisearch\Client;
use Meilisearch\Contracts\TasksQuery;
use Meilisearch\Exceptions\ApiException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Setono\SyliusMeilisearchPlugin\Message\Command\FinalizeIndexRebuild;
use Setono\SyliusMeilisearchPlugin\Resolver\IndexUid\RebuildUid;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

final class FinalizeIndexRebuildHandler
{
    public function __construct(
        private readonly Client $client,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function __invoke(FinalizeIndexRebuild $message): void
    {
        try {
            // A redelivery of this message must not enqueue a second swap: the first swap already
            // moved the fresh data into the live index, so swapping again would move it back out
            if ($this->swapInFlight($message->liveUids)) {
                $this->logger->warning('An index swap is already enqueued or processing for the "{index}" index, skipping this finalization', [
                    'index' => $message->index,
                    'liveUids' => $message->liveUids,
                ]);

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

                $swaps[] = [$liveUid, RebuildUid::from($liveUid)];
            }

            $this->client->swapIndexes($swaps);

            foreach ($message->liveUids as $liveUid) {
                // After the swap the rebuild uid holds the previous live data
                $this->client->deleteIndex(RebuildUid::from($liveUid));
            }
        } catch (\Throwable $e) {
            // Retrying is unsafe because swapIndexes is not idempotent: a retry after a partial
            // failure could swap the stale data back into the live index. Failing here leaves the
            // live index serving its current data, and the orphaned rebuild index is deleted when
            // the next rebuild starts.
            throw new UnrecoverableMessageHandlingException(message: $e->getMessage(), previous: $e);
        }

        $this->logger->info('Finalized the rebuild of the "{index}" index: the rebuild indexes have been swapped with the live indexes', [
            'index' => $message->index,
            'liveUids' => $message->liveUids,
        ]);
    }

    /**
     * @param list<string> $liveUids
     */
    private function swapInFlight(array $liveUids): bool
    {
        $query = new TasksQuery();
        $query->setTypes(['indexSwap']);
        $query->setStatuses(['enqueued', 'processing']);
        $query->setIndexUids($liveUids);

        return $this->client->getTasks($query)->getTotal() > 0;
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
