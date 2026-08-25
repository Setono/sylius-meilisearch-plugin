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
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

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
     * @param list<array<string, mixed>> $pendingSwapTasks
     *
     * @return ObjectProphecy<Client>
     */
    private function prophesizeClient(array $pendingSwapTasks = []): ObjectProphecy
    {
        $client = $this->prophesize(Client::class);
        $client
            ->getTasks(Argument::that(static function (TasksQuery $query): bool {
                $array = $query->toArray();

                return ($array['types'] ?? null) === 'indexSwap' && ($array['statuses'] ?? null) === 'enqueued,processing';
            }))
            ->willReturn(new TasksResults(['results' => $pendingSwapTasks, 'total' => count($pendingSwapTasks)]))
        ;

        return $client;
    }

    /**
     * @param list<string> $uids
     *
     * @return array<string, mixed>
     */
    private static function pendingSwapTask(array $uids): array
    {
        return [
            'uid' => 1,
            'type' => 'indexSwap',
            'status' => 'enqueued',
            'details' => ['swaps' => [['indexes' => $uids]]],
        ];
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

        $handler = new FinalizeIndexRebuildHandler($client->reveal());
        $handler(new FinalizeIndexRebuild('products', ['products__a', 'products__b'], self::REBUILD_ID));

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

        $handler = new FinalizeIndexRebuildHandler($client->reveal());
        $handler(new FinalizeIndexRebuild('products', ['products__a'], self::REBUILD_ID));
    }

    /**
     * @test
     */
    public function it_skips_when_a_swap_involving_its_live_uids_is_already_pending(): void
    {
        // A redelivered finalize message must not swap again: the second swap would move the fresh
        // data back out of the live index
        $client = $this->prophesizeClient([
            self::pendingSwapTask(['products__a', 'products__a__rebuild_' . self::REBUILD_ID]),
        ]);
        $client->swapIndexes(Argument::any())->shouldNotBeCalled();
        $client->deleteIndex(Argument::any())->shouldNotBeCalled();

        $handler = new FinalizeIndexRebuildHandler($client->reveal());
        $handler(new FinalizeIndexRebuild('products', ['products__a'], self::REBUILD_ID));
    }

    /**
     * @test
     */
    public function it_ignores_pending_swaps_of_unrelated_indexes(): void
    {
        // A swap task has no indexUid, so pending swaps are matched client side — one from another
        // project sharing the instance must not block this finalization
        $client = $this->prophesizeClient([
            self::pendingSwapTask(['other_project__products', 'other_project__products__rebuild_x']),
        ]);
        $client->getRawIndex('products__a')->willReturn(['uid' => 'products__a']);
        $client->swapIndexes([['products__a', 'products__a__rebuild_' . self::REBUILD_ID]])->shouldBeCalledOnce()->willReturn([]);
        $client->deleteIndex('products__a__rebuild_' . self::REBUILD_ID)->shouldBeCalledOnce()->willReturn([]);

        $handler = new FinalizeIndexRebuildHandler($client->reveal());
        $handler(new FinalizeIndexRebuild('products', ['products__a'], self::REBUILD_ID));
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

        $handler = new FinalizeIndexRebuildHandler($client->reveal());

        $this->expectException(UnrecoverableMessageHandlingException::class);

        $handler(new FinalizeIndexRebuild('products', ['products__a'], self::REBUILD_ID));
    }
}
