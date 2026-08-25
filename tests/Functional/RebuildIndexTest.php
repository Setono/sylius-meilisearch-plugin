<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Functional;

use Meilisearch\Client;
use Meilisearch\Exceptions\ApiException;
use Setono\SyliusMeilisearchPlugin\Command\IndexCommand;
use Setono\SyliusMeilisearchPlugin\Message\Command\Index;
use Setono\SyliusMeilisearchPlugin\Provider\IndexUids\IndexUidsProviderInterface;
use Setono\SyliusMeilisearchPlugin\Resolver\IndexUid\RebuildUid;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Covers the atomic full rebuild (see the FinalizeIndexRebuild message): a plain index run rebuilds
 * into rebuild indexes and swaps them with the live indexes, purging stale documents without any
 * search downtime.
 */
final class RebuildIndexTest extends FunctionalTestCase
{
    public function testAFullIndexRunPurgesStaleDocumentsWithoutSearchDowntime(): void
    {
        $client = $this->getMeilisearchClient();
        $liveUids = $this->getLiveUids();
        $liveUid = $liveUids[0];

        // Seed a document whose entity does not exist in the database, emulating index drift
        // (e.g. an entity deleted with plain SQL or leftovers from previous fixture loads)
        $this->waitForTask($client->index($liveUid)->addDocuments([[
            'id' => 'stale-functional-test-document',
            'name' => 'Stale functional test document',
        ]], 'id'));

        $this->dispatchIndexMessage();

        // While the rebuild's Meilisearch tasks are processed, the live index must keep serving
        // results: before the swap these are the previous documents (including the stale one),
        // after the atomic swap the fresh ones — but never an empty index
        $this->drainTasks($liveUids, function () use ($client, $liveUid): void {
            $hits = $client->index($liveUid)->search('')->getHits();
            self::assertNotEmpty($hits, 'The live index returned no hits during a rebuild, so search had downtime');
        });

        // The stale document was purged by the rebuild...
        try {
            $client->index($liveUid)->getDocument('stale-functional-test-document');
            self::fail('The stale document is still present in the live index after a full index run');
        } catch (ApiException $e) {
            self::assertSame(404, $e->httpStatus);
        }

        // ...while the fixture products are (still) searchable
        $stats = $client->index($liveUid)->stats();
        self::assertIsInt($stats['numberOfDocuments']);
        self::assertGreaterThan(0, $stats['numberOfDocuments']);
        self::assertNotEmpty($client->index($liveUid)->search('jeans')->getHits());
    }

    public function testItCreatesTheLiveIndexesOnTheFirstRebuild(): void
    {
        $client = $this->getMeilisearchClient();
        $liveUids = $this->getLiveUids();

        // Simulate the very first index run of an application: no live index exists yet. The swap
        // fails as a whole if either side of a pair is missing, so the rebuild has to create the
        // live index before swapping.
        foreach ($liveUids as $liveUid) {
            $this->waitForTask($client->deleteIndex($liveUid));
        }

        $this->dispatchIndexMessage();
        $this->drainTasks($liveUids);

        foreach ($liveUids as $liveUid) {
            $stats = $client->index($liveUid)->stats();
            self::assertIsInt($stats['numberOfDocuments']);
            self::assertGreaterThan(0, $stats['numberOfDocuments']);
        }
    }

    private function waitForTask(mixed $task): void
    {
        self::assertIsArray($task);
        self::assertIsInt($task['taskUid']);

        $this->getMeilisearchClient()->waitForTask($task['taskUid']);
    }

    private function getMeilisearchClient(): Client
    {
        /** @var Client $client */
        $client = self::getContainer()->get(Client::class);

        return $client;
    }

    /**
     * @return non-empty-list<string>
     */
    private function getLiveUids(): array
    {
        /** @var IndexUidsProviderInterface $indexUidsProvider */
        $indexUidsProvider = self::getContainer()->get(IndexUidsProviderInterface::class);

        $liveUids = $indexUidsProvider->get('products');
        self::assertNotEmpty($liveUids);

        return $liveUids;
    }

    private function dispatchIndexMessage(): void
    {
        /** @var MessageBusInterface $commandBus */
        $commandBus = self::getContainer()->get('setono_sylius_meilisearch.command_bus');

        // The test application's command bus is synchronous, so when this returns, all rebuild
        // batches and the finalizing swap have been enqueued as Meilisearch tasks in order
        $commandBus->dispatch(new Index('products'));
    }

    /**
     * Polls Meilisearch until it has processed every task belonging to the rebuild — the document
     * additions live on the rebuild uids, the swap and its cleanup on the live uids.
     *
     * @param list<string> $liveUids
     * @param \Closure(): void|null $onPoll called between polls (and once after the drain)
     */
    private function drainTasks(array $liveUids, ?\Closure $onPoll = null, int $timeout = 120): void
    {
        $uids = $liveUids;
        foreach ($liveUids as $liveUid) {
            $uids[] = RebuildUid::from($liveUid);
        }

        $query = IndexCommand::createTasksQuery($uids);
        $client = $this->getMeilisearchClient();

        $start = time();

        while ($client->getTasks($query)->getTotal() > 0) {
            if (null !== $onPoll) {
                $onPoll();
            }

            if (time() - $start > $timeout) {
                self::fail(sprintf('The rebuild tasks did not finish within %d seconds', $timeout));
            }

            usleep(100_000);
        }

        if (null !== $onPoll) {
            $onPoll();
        }
    }
}
