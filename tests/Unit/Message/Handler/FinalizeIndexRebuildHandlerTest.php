<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\Message\Handler;

use Meilisearch\Client;
use Meilisearch\Contracts\TasksQuery;
use Meilisearch\Contracts\TasksResults;
use Meilisearch\Endpoints\Indexes;
use Meilisearch\Exceptions\ApiException;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusMeilisearchPlugin\Indexer\Rebuild;
use Setono\SyliusMeilisearchPlugin\Message\Command\FinalizeIndexRebuild;
use Setono\SyliusMeilisearchPlugin\Message\Handler\FinalizeIndexRebuildHandler;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

/**
 * @covers \Setono\SyliusMeilisearchPlugin\Message\Handler\FinalizeIndexRebuildHandler
 */
final class FinalizeIndexRebuildHandlerTest extends TestCase
{
    use ProphecyTrait;

    private const REBUILD_ID = '20260825120000_ab12cd_ef01';

    private const EARLIER_REBUILD_ID = '20260825115900_123456_7890';

    private const LATER_REBUILD_ID = '20260825120100_123456_7890';

    private const STARTED_AT = '2026-08-25 12:00:00';

    private static function rebuildUid(string $liveUid, string $rebuildId = self::REBUILD_ID): string
    {
        return $liveUid . '__rebuild_' . $rebuildId;
    }

    private static function ledgerUid(): string
    {
        return self::rebuildUid('products__a') . '__ledger';
    }

    private static function clock(string $now = '2026-08-25 12:00:30'): MockClock
    {
        return new MockClock(new \DateTimeImmutable($now, new \DateTimeZone('UTC')));
    }

    /**
     * @param non-empty-list<string> $liveUids
     */
    private static function message(
        int $batches = 4,
        int $recorded = 0,
        ?string $progressedAt = null,
        int $idleChecks = 0,
        array $liveUids = ['products__a'],
    ): FinalizeIndexRebuild {
        return new FinalizeIndexRebuild(
            'products',
            new Rebuild(self::REBUILD_ID, $liveUids),
            new \DateTimeImmutable(self::STARTED_AT, new \DateTimeZone('UTC')),
            $batches,
            $recorded,
            null === $progressedAt ? null : new \DateTimeImmutable($progressedAt, new \DateTimeZone('UTC')),
            $idleChecks,
        );
    }

    private static function swapTask(string $status, string $lhs, string $rhs): array
    {
        return [
            'uid' => 1,
            'type' => 'indexSwap',
            'status' => $status,
            'details' => ['swaps' => [['indexes' => [$lhs, $rhs]]]],
        ];
    }

    /**
     * @param list<int>|null $recorded the number of recorded batches per completeness check, null when no batch has been recorded yet
     *
     * @return ObjectProphecy<Client>
     */
    private function prophesizeClient(
        ?array $recorded = [4],
        array $swapTasks = [],
        bool $pending = false,
        ?string $failure = null,
    ): ObjectProphecy {
        $client = $this->prophesize(Client::class);
        $client
            ->getTasks(Argument::that(static fn (TasksQuery $query): bool => 'indexSwap' === ($query->toArray()['types'] ?? null)))
            ->willReturn(new TasksResults(['results' => $swapTasks, 'next' => null]))
        ;

        $ledger = $this->prophesize(Indexes::class);
        if (null === $recorded) {
            $ledger->stats()->willThrow(new ApiException(new Response(404), null));
        } else {
            $ledger->stats()->willReturn(...array_map(static fn (int $count): array => ['numberOfDocuments' => $count], $recorded));
        }
        $client->index(self::ledgerUid())->willReturn($ledger->reveal());

        // The newest task bounds the tasks the swap waits for
        $client
            ->getTasks(Argument::that(static fn (TasksQuery $query): bool => ['limit' => 1] === $query->toArray()))
            ->willReturn(new TasksResults(['results' => [['uid' => 100]]]))
        ;
        $client
            ->getTasks(Argument::that(static fn (TasksQuery $query): bool => self::isRunTasksQuery($query, 'enqueued,processing')))
            ->willReturn(new TasksResults(['results' => $pending ? [['uid' => 99, 'status' => 'enqueued']] : []]))
        ;
        $client
            ->getTasks(Argument::that(static fn (TasksQuery $query): bool => self::isRunTasksQuery($query, 'failed')))
            ->willReturn(new TasksResults(['results' => null === $failure ? [] : [[
                'uid' => 42,
                'status' => 'failed',
                'error' => ['message' => $failure, 'code' => 'invalid_document_id', 'type' => 'invalid_request', 'link' => null],
            ]]]))
        ;

        return $client;
    }

    private static function isRunTasksQuery(TasksQuery $query, string $statuses): bool
    {
        $array = $query->toArray();
        $indexUids = $array['indexUids'] ?? null;

        return $statuses === ($array['statuses'] ?? null) &&
            100 === ($array['from'] ?? null) &&
            is_string($indexUids) && str_contains($indexUids, '__rebuild_' . self::REBUILD_ID);
    }

    /**
     * @param ObjectProphecy<Client> $client
     */
    private static function expectNoSwap(ObjectProphecy $client): void
    {
        $client->swapIndexes(Argument::any())->shouldNotBeCalled();
    }

    /**
     * @param ObjectProphecy<Client> $client
     * @param list<string> $liveUids
     */
    private static function expectRebuildIndexesDeleted(ObjectProphecy $client, array $liveUids = ['products__a']): void
    {
        foreach ($liveUids as $liveUid) {
            $client->deleteIndex(self::rebuildUid($liveUid))->shouldBeCalledOnce()->willReturn([]);
        }
        $client->deleteIndex(self::ledgerUid())->shouldBeCalledOnce()->willReturn([]);
    }

    /**
     * @return ObjectProphecy<MessageBusInterface>
     */
    private function prophesizeUnusedBus(): ObjectProphecy
    {
        $commandBus = $this->prophesize(MessageBusInterface::class);
        $commandBus->dispatch(Argument::cetera())->shouldNotBeCalled();

        return $commandBus;
    }

    /**
     * @param (\Closure(FinalizeIndexRebuild): bool)|null $message
     *
     * @return ObjectProphecy<MessageBusInterface>
     */
    private function prophesizeReschedulingBus(?\Closure $message, int $delay): ObjectProphecy
    {
        $commandBus = $this->prophesize(MessageBusInterface::class);
        $commandBus
            ->dispatch(null === $message ? Argument::type(FinalizeIndexRebuild::class) : Argument::that($message), [new DelayStamp($delay * 1000)])
            ->shouldBeCalledOnce()
            ->willReturn(new Envelope(new \stdClass()))
        ;

        return $commandBus;
    }

    /**
     * @test
     */
    public function it_swaps_all_pairs_in_one_atomic_call_and_deletes_the_rebuild_indexes_afterwards(): void
    {
        $calls = [];

        $client = $this->prophesizeClient();
        $client->getRawIndex('products__a')->willReturn(['uid' => 'products__a']);
        $client->getRawIndex('products__b')->willReturn(['uid' => 'products__b']);
        $client->createIndex(Argument::any())->shouldNotBeCalled();
        $client
            ->swapIndexes([
                ['products__a', self::rebuildUid('products__a')],
                ['products__b', self::rebuildUid('products__b')],
            ])
            ->shouldBeCalledOnce()
            ->will(function () use (&$calls): array {
                $calls[] = 'swap';

                return [];
            })
        ;
        $client->deleteIndex(Argument::type('string'))->shouldBeCalledTimes(3)->will(function (array $args) use (&$calls): array {
            $uid = $args[0];
            \assert(is_string($uid));
            $calls[] = 'delete ' . $uid;

            return [];
        });

        $handler = new FinalizeIndexRebuildHandler($client->reveal(), $this->prophesizeUnusedBus()->reveal(), self::clock());
        $handler(self::message(liveUids: ['products__a', 'products__b']));

        // The rebuild indexes hold the previous live data after the swap, so they must be deleted last
        self::assertSame([
            'swap',
            'delete ' . self::rebuildUid('products__a'),
            'delete ' . self::rebuildUid('products__b'),
            'delete ' . self::ledgerUid(),
        ], $calls);
    }

    /**
     * @test
     */
    public function it_creates_a_missing_live_index_before_swapping(): void
    {
        // On the very first rebuild the live index does not exist, and the swap fails as a whole
        // when either side of a pair is missing
        $client = $this->prophesizeClient();
        $client->getRawIndex('products__a')->willThrow(new ApiException(new Response(404), null));
        $client->createIndex('products__a')->shouldBeCalledOnce()->willReturn([]);
        $client->swapIndexes([['products__a', self::rebuildUid('products__a')]])->shouldBeCalledOnce()->willReturn([]);
        self::expectRebuildIndexesDeleted($client);

        $handler = new FinalizeIndexRebuildHandler($client->reveal(), $this->prophesizeUnusedBus()->reveal(), self::clock());
        $handler(self::message());
    }

    /**
     * @test
     */
    public function it_reschedules_itself_with_a_throughput_based_delay_while_batches_are_missing(): void
    {
        // A transiently failed batch is redelivered behind this message on any transport, so the
        // swap must wait until every batch is recorded in the run's ledger
        $client = $this->prophesizeClient(recorded: [2]);
        self::expectNoSwap($client);
        $client->deleteIndex(Argument::any())->shouldNotBeCalled();

        // 2 batches in 30 seconds leave 2 batches for another 30 seconds
        $commandBus = $this->prophesizeReschedulingBus(
            static fn (FinalizeIndexRebuild $message): bool => 2 === $message->recorded &&
                0 === $message->idleChecks &&
                $message->progressedAt?->getTimestamp() === (new \DateTimeImmutable('2026-08-25 12:00:30', new \DateTimeZone('UTC')))->getTimestamp() &&
                self::REBUILD_ID === $message->rebuild->id,
            30,
        );

        $handler = new FinalizeIndexRebuildHandler($client->reveal(), $commandBus->reveal(), self::clock());
        $handler(self::message());
    }

    /**
     * @test
     */
    public function it_checks_again_soon_when_nothing_has_been_recorded_yet(): void
    {
        // Even when no worker has reached the batches yet, a small rebuild must not wait for minutes
        $client = $this->prophesizeClient(recorded: null);
        self::expectNoSwap($client);

        $commandBus = $this->prophesizeReschedulingBus(
            static fn (FinalizeIndexRebuild $message): bool => 0 === $message->recorded && 1 === $message->idleChecks,
            5,
        );

        $handler = new FinalizeIndexRebuildHandler($client->reveal(), $commandBus->reveal(), self::clock());
        $handler(self::message());
    }

    /**
     * @test
     */
    public function it_backs_off_exponentially_without_progress(): void
    {
        $client = $this->prophesizeClient(recorded: [2]);

        $commandBus = $this->prophesizeReschedulingBus(
            static fn (FinalizeIndexRebuild $message): bool => 3 === $message->idleChecks,
            20,
        );

        $handler = new FinalizeIndexRebuildHandler($client->reveal(), $commandBus->reveal(), self::clock());
        $handler(self::message(recorded: 2, progressedAt: '2026-08-25 12:00:10', idleChecks: 2));
    }

    /**
     * @test
     */
    public function it_keeps_waiting_for_the_first_batch_however_long_it_takes(): void
    {
        // The batches may be queued behind a backlog of other messages — that is no sign of loss
        $client = $this->prophesizeClient(recorded: null);
        self::expectNoSwap($client);
        $client->deleteIndex(Argument::any())->shouldNotBeCalled();

        $commandBus = $this->prophesizeReschedulingBus(null, 60);

        $handler = new FinalizeIndexRebuildHandler($client->reveal(), $commandBus->reveal(), self::clock('2026-08-25 18:00:00'));
        $handler(self::message(idleChecks: 50));
    }

    /**
     * @test
     */
    public function it_gives_up_when_no_batch_has_been_recorded_for_two_hours(): void
    {
        // The missing batches are lost (e.g. in the failure transport) — an incomplete rebuild must
        // never be swapped live
        $client = $this->prophesizeClient(recorded: [2]);
        self::expectNoSwap($client);
        self::expectRebuildIndexesDeleted($client);

        $handler = new FinalizeIndexRebuildHandler($client->reveal(), $this->prophesizeUnusedBus()->reveal(), self::clock('2026-08-25 14:30:00'));
        $handler(self::message(recorded: 2, progressedAt: '2026-08-25 12:20:00', idleChecks: 30));
    }

    /**
     * @test
     */
    public function it_keeps_waiting_for_a_redelivery_within_two_hours(): void
    {
        // A batch of a crashed worker is only redelivered after the transport's redelivery timeout
        $client = $this->prophesizeClient(recorded: [2]);
        self::expectNoSwap($client);
        $client->deleteIndex(Argument::any())->shouldNotBeCalled();

        $commandBus = $this->prophesizeReschedulingBus(null, 60);

        $handler = new FinalizeIndexRebuildHandler($client->reveal(), $commandBus->reveal(), self::clock('2026-08-25 13:30:00'));
        $handler(self::message(recorded: 2, progressedAt: '2026-08-25 12:20:00', idleChecks: 20));
    }

    /**
     * @test
     */
    public function it_gives_up_when_the_rebuild_has_gone_stale(): void
    {
        $client = $this->prophesizeClient(recorded: [3]);
        self::expectNoSwap($client);
        self::expectRebuildIndexesDeleted($client);

        $handler = new FinalizeIndexRebuildHandler($client->reveal(), $this->prophesizeUnusedBus()->reveal(), self::clock('2026-08-26 12:00:00'));
        $handler(self::message(recorded: 2));
    }

    /**
     * @test
     */
    public function it_waits_until_meilisearch_has_processed_the_tasks_of_the_run(): void
    {
        // Only a processed task is known not to have failed
        $client = $this->prophesizeClient(pending: true);
        self::expectNoSwap($client);

        $commandBus = $this->prophesizeReschedulingBus(static fn (FinalizeIndexRebuild $message): bool => 4 === $message->recorded, 5);

        $handler = new FinalizeIndexRebuildHandler($client->reveal(), $commandBus->reveal(), self::clock());
        $handler(self::message(recorded: 3));
    }

    /**
     * @test
     */
    public function it_never_swaps_a_rebuild_with_a_failed_task(): void
    {
        // e.g. a batch whose documents Meilisearch rejected: swapping would drop them from search
        $client = $this->prophesizeClient(failure: 'Document identifier is invalid');
        self::expectNoSwap($client);
        self::expectRebuildIndexesDeleted($client);

        $handler = new FinalizeIndexRebuildHandler($client->reveal(), $this->prophesizeUnusedBus()->reveal(), self::clock());
        $handler(self::message());
    }

    /**
     * @test
     */
    public function it_never_swaps_twice(): void
    {
        // A redelivered, retried or replayed finalization must not swap again: the second swap would
        // put the previous data back live. Here a later swap has renamed the live uid in the details
        // of this run's swap, which still names this run's rebuild uid.
        $client = $this->prophesizeClient(swapTasks: [
            self::swapTask('succeeded', self::rebuildUid('products__a', self::LATER_REBUILD_ID), self::rebuildUid('products__a')),
        ]);
        self::expectNoSwap($client);
        self::expectRebuildIndexesDeleted($client);

        $handler = new FinalizeIndexRebuildHandler($client->reveal(), $this->prophesizeUnusedBus()->reveal(), self::clock());
        $handler(self::message());
    }

    /**
     * @test
     */
    public function it_is_superseded_by_a_later_rebuild_that_has_swapped_already(): void
    {
        // Swapping now would replace the later rebuild's fresher documents and settings
        $client = $this->prophesizeClient(swapTasks: [
            self::swapTask('enqueued', 'products__a', self::rebuildUid('products__a', self::LATER_REBUILD_ID)),
        ]);
        self::expectNoSwap($client);
        self::expectRebuildIndexesDeleted($client);

        $handler = new FinalizeIndexRebuildHandler($client->reveal(), $this->prophesizeUnusedBus()->reveal(), self::clock());
        $handler(self::message());
    }

    /**
     * @test
     */
    public function it_swaps_after_an_earlier_rebuild(): void
    {
        // Meilisearch processes the swaps in order, so this later rebuild ends up live
        $client = $this->prophesizeClient(swapTasks: [
            self::swapTask('enqueued', 'products__a', self::rebuildUid('products__a', self::EARLIER_REBUILD_ID)),
            self::swapTask('succeeded', 'other_project__products', 'other_project__products__rebuild_' . self::LATER_REBUILD_ID),
        ]);
        $client->getRawIndex('products__a')->willReturn(['uid' => 'products__a']);
        $client->swapIndexes([['products__a', self::rebuildUid('products__a')]])->shouldBeCalledOnce()->willReturn([]);
        self::expectRebuildIndexesDeleted($client);

        $handler = new FinalizeIndexRebuildHandler($client->reveal(), $this->prophesizeUnusedBus()->reveal(), self::clock());
        $handler(self::message());
    }

    /**
     * @test
     */
    public function it_loops_instead_of_recursing_on_a_synchronous_bus(): void
    {
        // The first check finds one batch missing, the second finds the rebuild complete
        $client = $this->prophesizeClient(recorded: [3, 4]);
        $client->getRawIndex('products__a')->willReturn(['uid' => 'products__a']);
        $client->swapIndexes([['products__a', self::rebuildUid('products__a')]])->shouldBeCalledOnce()->willReturn([]);
        self::expectRebuildIndexesDeleted($client);

        $clock = self::clock();

        // Without transport routing, the bus handles the rescheduled check immediately and ignores the delay
        $commandBus = new class() implements MessageBusInterface {
            public ?FinalizeIndexRebuildHandler $handler = null;

            public int $dispatched = 0;

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                ++$this->dispatched;

                \assert(null !== $this->handler && $message instanceof FinalizeIndexRebuild);
                ($this->handler)($message);

                return new Envelope($message);
            }
        };

        $handler = new FinalizeIndexRebuildHandler($client->reveal(), $commandBus, $clock);
        $commandBus->handler = $handler;
        $handler(self::message(recorded: 2));

        self::assertSame(1, $commandBus->dispatched);
        // The handler waited the delay itself: 3 of 4 batches in 30 seconds leave one for 10 seconds
        self::assertEquals(new \DateTimeImmutable('2026-08-25 12:00:40', new \DateTimeZone('UTC')), $clock->now());
    }

    /**
     * @test
     */
    public function it_lets_errors_propagate_for_the_transport_to_retry(): void
    {
        // A retry is safe: a swap enqueued before the error is found in the task history
        $client = $this->prophesizeClient();
        $client->getRawIndex('products__a')->willReturn(['uid' => 'products__a']);
        $client->swapIndexes(Argument::any())->willThrow(new \RuntimeException('Connection failed'));

        $handler = new FinalizeIndexRebuildHandler($client->reveal(), $this->prophesizeUnusedBus()->reveal(), self::clock());

        $this->expectExceptionObject(new \RuntimeException('Connection failed'));

        $handler(self::message());
    }
}
