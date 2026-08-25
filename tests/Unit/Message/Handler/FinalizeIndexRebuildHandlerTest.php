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

    private static function notFound(): ApiException
    {
        return new ApiException(new Response(404), null);
    }

    /**
     * @return ObjectProphecy<Client>
     */
    private function prophesizeClientWithoutSwapsInFlight(): ObjectProphecy
    {
        $tasks = $this->prophesize(TasksResults::class);
        $tasks->getTotal()->willReturn(0);

        $client = $this->prophesize(Client::class);
        $client->getTasks(Argument::type(TasksQuery::class))->willReturn($tasks->reveal());

        return $client;
    }

    /**
     * @test
     */
    public function it_swaps_all_pairs_in_one_atomic_call_and_deletes_the_rebuild_indexes_afterwards(): void
    {
        $calls = [];

        $client = $this->prophesizeClientWithoutSwapsInFlight();
        $client->getRawIndex('products__a')->willReturn(['uid' => 'products__a']);
        $client->getRawIndex('products__b')->willReturn(['uid' => 'products__b']);
        $client->createIndex(Argument::any())->shouldNotBeCalled();
        $client
            ->swapIndexes([['products__a', 'products__a__rebuild'], ['products__b', 'products__b__rebuild']])
            ->shouldBeCalledOnce()
            ->will(function () use (&$calls): array {
                $calls[] = 'swap';

                return [];
            })
        ;
        $client->deleteIndex('products__a__rebuild')->shouldBeCalledOnce()->will(function () use (&$calls): array {
            $calls[] = 'delete products__a__rebuild';

            return [];
        });
        $client->deleteIndex('products__b__rebuild')->shouldBeCalledOnce()->will(function () use (&$calls): array {
            $calls[] = 'delete products__b__rebuild';

            return [];
        });

        $handler = new FinalizeIndexRebuildHandler($client->reveal());
        $handler(new FinalizeIndexRebuild('products', ['products__a', 'products__b']));

        // The rebuild indexes hold the previous live data after the swap, so they must be deleted last
        self::assertSame(['swap', 'delete products__a__rebuild', 'delete products__b__rebuild'], $calls);
    }

    /**
     * @test
     */
    public function it_creates_a_missing_live_index_before_swapping(): void
    {
        // On the very first rebuild the live index does not exist, and the swap fails as a whole
        // when either side of a pair is missing
        $client = $this->prophesizeClientWithoutSwapsInFlight();
        $client->getRawIndex('products__a')->willThrow(self::notFound());
        $client->createIndex('products__a')->shouldBeCalledOnce()->willReturn([]);
        $client->swapIndexes([['products__a', 'products__a__rebuild']])->shouldBeCalledOnce()->willReturn([]);
        $client->deleteIndex('products__a__rebuild')->shouldBeCalledOnce()->willReturn([]);

        $handler = new FinalizeIndexRebuildHandler($client->reveal());
        $handler(new FinalizeIndexRebuild('products', ['products__a']));
    }

    /**
     * @test
     */
    public function it_skips_when_a_swap_is_already_in_flight(): void
    {
        // A redelivered finalize message must not swap again: the second swap would move the fresh
        // data back out of the live index
        $tasks = $this->prophesize(TasksResults::class);
        $tasks->getTotal()->willReturn(1);

        $client = $this->prophesize(Client::class);
        $client
            ->getTasks(Argument::that(
                static fn (TasksQuery $query): bool => [
                    'statuses' => 'enqueued,processing',
                    'types' => 'indexSwap',
                    'indexUids' => 'products__a',
                ] === $query->toArray(),
            ))
            ->shouldBeCalledOnce()
            ->willReturn($tasks->reveal())
        ;
        $client->swapIndexes(Argument::any())->shouldNotBeCalled();
        $client->deleteIndex(Argument::any())->shouldNotBeCalled();

        $handler = new FinalizeIndexRebuildHandler($client->reveal());
        $handler(new FinalizeIndexRebuild('products', ['products__a']));
    }

    /**
     * @test
     */
    public function it_wraps_client_failures_in_an_unrecoverable_exception(): void
    {
        // Retrying is unsafe because the swap is not idempotent, so the message must never be retried
        $client = $this->prophesizeClientWithoutSwapsInFlight();
        $client->getRawIndex('products__a')->willReturn(['uid' => 'products__a']);
        $client->swapIndexes(Argument::any())->willThrow(new \RuntimeException('Connection failed'));

        $handler = new FinalizeIndexRebuildHandler($client->reveal());

        $this->expectException(UnrecoverableMessageHandlingException::class);

        $handler(new FinalizeIndexRebuild('products', ['products__a']));
    }
}
