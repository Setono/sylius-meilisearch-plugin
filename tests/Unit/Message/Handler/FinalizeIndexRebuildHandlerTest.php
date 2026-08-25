<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\Message\Handler;

use Meilisearch\Client;
use Meilisearch\Contracts\TasksQuery;
use Meilisearch\Contracts\TasksResults;
use Meilisearch\Exceptions\ApiException;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusMeilisearchPlugin\Message\Command\FinalizeIndexRebuild;
use Setono\SyliusMeilisearchPlugin\Message\Handler\FinalizeIndexRebuildHandler;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

/**
 * @covers \Setono\SyliusMeilisearchPlugin\Message\Handler\FinalizeIndexRebuildHandler
 */
final class FinalizeIndexRebuildHandlerTest extends TestCase
{
    use ProphecyTrait;

    private const REBUILD_ID = '20260825120000_ab12cd';

    private static function notFound(): ApiException
    {
        return new ApiException(new Response(404), null);
    }

    /**
     * @param list<string> $liveUids
     */
    private static function message(
        int $expectedTasks = 2,
        int $completedTasks = 0,
        int $stagnantChecks = 0,
        ?\DateTimeImmutable $startedAt = null,
        array $liveUids = ['products__a'],
    ): FinalizeIndexRebuild {
        return new FinalizeIndexRebuild(
            'products',
            $liveUids,
            self::REBUILD_ID,
            $startedAt ?? new \DateTimeImmutable('-30 seconds', new \DateTimeZone('UTC')),
            $expectedTasks,
            $completedTasks,
            $stagnantChecks,
        );
    }

    /**
     * @param list<array<string, mixed>> $pendingSwapTasks
     *
     * @return ObjectProphecy<Client>
     */
    private function prophesizeClient(int $arrivedTasks = 2, array $pendingSwapTasks = []): ObjectProphecy
    {
        $client = $this->prophesize(Client::class);
        $client
            ->getTasks(Argument::that(static function (TasksQuery $query): bool {
                $array = $query->toArray();

                return ($array['types'] ?? null) === 'indexSwap' && ($array['statuses'] ?? null) === 'enqueued,processing';
            }))
            ->willReturn(new TasksResults(['results' => $pendingSwapTasks, 'total' => count($pendingSwapTasks)]))
        ;
        // The completeness check counts the run's document-addition tasks on its rebuild uids
        $client
            ->getTasks(Argument::that(static function (TasksQuery $query): bool {
                $array = $query->toArray();

                $indexUids = $array['indexUids'] ?? '';

                return ($array['types'] ?? null) === 'documentAdditionOrUpdate' && is_string($indexUids) && str_contains($indexUids, '__rebuild_' . self::REBUILD_ID);
            }))
            ->willReturn(new TasksResults(['results' => [], 'total' => $arrivedTasks]))
        ;

        return $client;
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
     * @test
     */
    public function it_swaps_all_pairs_in_one_atomic_call_and_deletes_the_rebuild_indexes_afterwards(): void
    {
        $calls = [];

        $client = $this->prophesizeClient(arrivedTasks: 4);
        $client->getRawIndex('products__a')->willReturn(['uid' => 'products__a']);
        $client->getRawIndex('products__b')->willReturn(['uid' => 'products__b']);
        $client->createIndex(Argument::any())->shouldNotBeCalled();
        $client
            ->swapIndexes([
                ['products__a', 'products__a__rebuild_' . self::REBUILD_ID],
                ['products__b', 'products__b__rebuild_' . self::REBUILD_ID],
            ])
            ->shouldBeCalledOnce()
            ->will(function () use (&$calls): array {
                $calls[] = 'swap';

                return [];
            })
        ;
        $client->deleteIndex('products__a__rebuild_' . self::REBUILD_ID)->shouldBeCalledOnce()->will(function () use (&$calls): array {
            $calls[] = 'delete a';

            return [];
        });
        $client->deleteIndex('products__b__rebuild_' . self::REBUILD_ID)->shouldBeCalledOnce()->will(function () use (&$calls): array {
            $calls[] = 'delete b';

            return [];
        });

        $handler = new FinalizeIndexRebuildHandler($client->reveal(), $this->prophesizeUnusedBus()->reveal());
        $handler(self::message(expectedTasks: 4, liveUids: ['products__a', 'products__b']));

        // The rebuild indexes hold the previous live data after the swap, so they must be deleted last
        self::assertSame(['swap', 'delete a', 'delete b'], $calls);
    }

    /**
     * @test
     */
    public function it_creates_a_missing_live_index_before_swapping(): void
    {
        // On the very first rebuild the live index does not exist, and the swap fails as a whole
        // when either side of a pair is missing
        $client = $this->prophesizeClient();
        $client->getRawIndex('products__a')->willThrow(self::notFound());
        $client->createIndex('products__a')->shouldBeCalledOnce()->willReturn([]);
        $client->swapIndexes([['products__a', 'products__a__rebuild_' . self::REBUILD_ID]])->shouldBeCalledOnce()->willReturn([]);
        $client->deleteIndex('products__a__rebuild_' . self::REBUILD_ID)->shouldBeCalledOnce()->willReturn([]);

        $handler = new FinalizeIndexRebuildHandler($client->reveal(), $this->prophesizeUnusedBus()->reveal());
        $handler(self::message());
    }

    /**
     * @test
     */
    public function it_reschedules_itself_while_batch_tasks_are_still_missing(): void
    {
        // A transiently failed batch is redelivered behind this message on any transport, so the
        // swap must wait until the run's task ledger is complete
        $client = $this->prophesizeClient(arrivedTasks: 2);
        $client->swapIndexes(Argument::any())->shouldNotBeCalled();
        $client->deleteIndex(Argument::any())->shouldNotBeCalled();

        $commandBus = $this->prophesize(MessageBusInterface::class);
        $commandBus
            ->dispatch(
                Argument::that(
                    static fn (FinalizeIndexRebuild $message): bool => 4 === $message->expectedTasks &&
                    2 === $message->completedTasks &&
                    0 === $message->stagnantChecks &&
                    self::REBUILD_ID === $message->rebuildId,
                ),
                Argument::that(static function (array $stamps): bool {
                    $stamp = $stamps[0] ?? null;

                    return $stamp instanceof DelayStamp && $stamp->getDelay() >= 5_000 && $stamp->getDelay() <= 300_000;
                }),
            )
            ->shouldBeCalledOnce()
            ->willReturn(new Envelope(new \stdClass()))
        ;

        $handler = new FinalizeIndexRebuildHandler($client->reveal(), $commandBus->reveal());
        $handler(self::message(expectedTasks: 4));
    }

    /**
     * @test
     */
    public function it_waits_the_maximum_delay_when_no_task_has_arrived_yet(): void
    {
        // No throughput observed yet — most likely the workers have not started; checking again
        // quickly would only burn through the stagnation allowance
        $client = $this->prophesizeClient(arrivedTasks: 0);

        $commandBus = $this->prophesize(MessageBusInterface::class);
        $commandBus
            ->dispatch(
                Argument::type(FinalizeIndexRebuild::class),
                Argument::that(static function (array $stamps): bool {
                    $stamp = $stamps[0] ?? null;

                    return $stamp instanceof DelayStamp && 300_000 === $stamp->getDelay();
                }),
            )
            ->shouldBeCalledOnce()
            ->willReturn(new Envelope(new \stdClass()))
        ;

        $handler = new FinalizeIndexRebuildHandler($client->reveal(), $commandBus->reveal());
        $handler(self::message(expectedTasks: 4));
    }

    /**
     * @test
     */
    public function it_resets_the_stagnation_counter_when_new_tasks_arrive(): void
    {
        $client = $this->prophesizeClient(arrivedTasks: 3);

        $commandBus = $this->prophesize(MessageBusInterface::class);
        $commandBus
            ->dispatch(
                Argument::that(static fn (FinalizeIndexRebuild $message): bool => 3 === $message->completedTasks && 0 === $message->stagnantChecks),
                Argument::type('array'),
            )
            ->shouldBeCalledOnce()
            ->willReturn(new Envelope(new \stdClass()))
        ;

        $handler = new FinalizeIndexRebuildHandler($client->reveal(), $commandBus->reveal());
        $handler(self::message(expectedTasks: 4, completedTasks: 2, stagnantChecks: 9));
    }

    /**
     * @test
     */
    public function it_gives_up_after_too_many_checks_without_progress(): void
    {
        // The missing batches are lost (e.g. in the failure transport) — an incomplete rebuild
        // must never be swapped live
        $client = $this->prophesizeClient(arrivedTasks: 2);
        $client->swapIndexes(Argument::any())->shouldNotBeCalled();
        $client->deleteIndex(Argument::any())->shouldNotBeCalled();

        $handler = new FinalizeIndexRebuildHandler($client->reveal(), $this->prophesizeUnusedBus()->reveal());
        $handler(self::message(expectedTasks: 4, completedTasks: 2, stagnantChecks: 9));
    }

    /**
     * @test
     */
    public function it_gives_up_when_the_rebuild_has_gone_stale(): void
    {
        $client = $this->prophesizeClient(arrivedTasks: 2);
        $client->swapIndexes(Argument::any())->shouldNotBeCalled();

        $handler = new FinalizeIndexRebuildHandler($client->reveal(), $this->prophesizeUnusedBus()->reveal());
        $handler(self::message(
            expectedTasks: 4,
            startedAt: new \DateTimeImmutable('-25 hours', new \DateTimeZone('UTC')),
        ));
    }

    /**
     * @test
     */
    public function it_skips_when_a_swap_involving_its_live_uids_is_already_pending(): void
    {
        // A redelivered finalize message must not swap again: the second swap would move the fresh
        // data back out of the live index
        $client = $this->prophesizeClient(pendingSwapTasks: [[
            'uid' => 1,
            'type' => 'indexSwap',
            'status' => 'enqueued',
            'details' => ['swaps' => [['indexes' => ['products__a', 'products__a__rebuild_' . self::REBUILD_ID]]]],
        ]]);
        $client->swapIndexes(Argument::any())->shouldNotBeCalled();
        $client->deleteIndex(Argument::any())->shouldNotBeCalled();

        $handler = new FinalizeIndexRebuildHandler($client->reveal(), $this->prophesizeUnusedBus()->reveal());
        $handler(self::message());
    }

    /**
     * @test
     */
    public function it_ignores_pending_swaps_of_unrelated_indexes(): void
    {
        // A swap task has no indexUid, so pending swaps are matched client side — one from another
        // project sharing the instance must not block this finalization
        $client = $this->prophesizeClient(pendingSwapTasks: [[
            'uid' => 1,
            'type' => 'indexSwap',
            'status' => 'enqueued',
            'details' => ['swaps' => [['indexes' => ['other_project__products', 'other_project__products__rebuild_x']]]],
        ]]);
        $client->getRawIndex('products__a')->willReturn(['uid' => 'products__a']);
        $client->swapIndexes([['products__a', 'products__a__rebuild_' . self::REBUILD_ID]])->shouldBeCalledOnce()->willReturn([]);
        $client->deleteIndex('products__a__rebuild_' . self::REBUILD_ID)->shouldBeCalledOnce()->willReturn([]);

        $handler = new FinalizeIndexRebuildHandler($client->reveal(), $this->prophesizeUnusedBus()->reveal());
        $handler(self::message());
    }

    /**
     * @test
     */
    public function it_wraps_client_failures_in_an_unrecoverable_exception(): void
    {
        // Retrying is unsafe because the swap is not idempotent, so the message must never be retried
        $client = $this->prophesizeClient();
        $client->getRawIndex('products__a')->willReturn(['uid' => 'products__a']);
        $client->swapIndexes(Argument::any())->willThrow(new \RuntimeException('Connection failed'));

        $handler = new FinalizeIndexRebuildHandler($client->reveal(), $this->prophesizeUnusedBus()->reveal());

        $this->expectException(UnrecoverableMessageHandlingException::class);

        $handler(self::message());
    }
}
