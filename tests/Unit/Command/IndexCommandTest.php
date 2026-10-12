<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\Command;

use Meilisearch\Client;
use Meilisearch\Contracts\TasksQuery;
use Meilisearch\Contracts\TasksResults;
use Meilisearch\Endpoints\Indexes;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusMeilisearchPlugin\Command\IndexCommand;
use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\Config\IndexRegistryInterface;
use Setono\SyliusMeilisearchPlugin\Document\Product as ProductDocument;
use Setono\SyliusMeilisearchPlugin\Message\Command\Index as IndexMessage;
use Setono\SyliusMeilisearchPlugin\Provider\IndexUids\IndexUidsProviderInterface;
use Setono\SyliusMeilisearchPlugin\Tests\Application\Entity\Product;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @covers \Setono\SyliusMeilisearchPlugin\Command\IndexCommand
 */
final class IndexCommandTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_prints_the_resolved_uids_and_dispatches_one_message_per_index(): void
    {
        $index = new Index('products', ProductDocument::class, [Product::class], new Container());

        $commandBus = $this->prophesize(MessageBusInterface::class);
        $commandBus
            ->dispatch(Argument::that(static fn (IndexMessage $message): bool => 'products' === $message->index))
            ->shouldBeCalledOnce()
            ->willReturn(new Envelope(new \stdClass()))
        ;

        $indexRegistry = $this->prophesize(IndexRegistryInterface::class);
        $indexRegistry->getNames()->willReturn(['products']);
        $indexRegistry->has('products')->willReturn(true);
        $indexRegistry->get('products')->willReturn($index);

        $indexUidsProvider = $this->prophesize(IndexUidsProviderInterface::class);
        $indexUidsProvider->get('products')->willReturn(['products__fashion_web__en_us__usd']);

        $command = new IndexCommand(
            $commandBus->reveal(),
            $indexRegistry->reveal(),
            $this->prophesize(Client::class)->reveal(),
            $indexUidsProvider->reveal(),
        );

        $tester = new CommandTester($command);
        $tester->execute(['indexes' => ['products']]);

        // Names each resolved index uid
        self::assertStringContainsString('products__fashion_web__en_us__usd', $tester->getDisplay());
    }

    /**
     * @test
     */
    public function it_waits_until_the_rebuild_it_requested_has_been_swapped_live(): void
    {
        $client = $this->prophesize(Client::class);

        $stats = $this->prophesize(Indexes::class);
        $stats->stats()->willReturn(['numberOfDocuments' => 8]);
        // The summary reports the live index, which holds the fresh documents after the swap
        $client->index('products__fashion_web__en_us__usd')->willReturn($stats->reveal());

        // The rebuild uids embed a per-run id the command cannot know, but also the id of the request
        $command = $this->createCommand($client, static fn (string $requestId): TasksResults => new TasksResults(['results' => [
            // The swap of another rebuild of the same index does not count
            self::swapTask('succeeded', 'products__fashion_web__en_us__usd__rebuild_20260825115900_000000_0000'),
            self::swapTask('succeeded', 'products__fashion_web__en_us__usd__rebuild_20260825120000_' . $requestId . '_ef01'),
        ]]));

        $tester = new CommandTester($command);
        $tester->execute(['indexes' => ['products'], '--wait' => true]);

        self::assertStringContainsString('8', $tester->getDisplay());
    }

    /**
     * @test
     */
    public function it_fails_when_the_swap_of_the_rebuild_it_requested_failed(): void
    {
        $command = $this->createCommand(
            $this->prophesize(Client::class),
            static fn (string $requestId): TasksResults => new TasksResults(['results' => [
                self::swapTask('failed', 'products__fashion_web__en_us__usd__rebuild_20260825120000_' . $requestId . '_ef01'),
            ]]),
        );

        $tester = new CommandTester($command);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Swapping the rebuilt "products" index live failed: Index not found');

        $tester->execute(['indexes' => ['products'], '--wait' => true]);
    }

    private static function swapTask(string $status, string $rebuildUid): array
    {
        return [
            'uid' => 1,
            'type' => 'indexSwap',
            'status' => $status,
            'details' => ['swaps' => [['indexes' => ['products__fashion_web__en_us__usd', $rebuildUid]]]],
            'error' => 'failed' === $status ? ['message' => 'Index not found', 'code' => 'index_not_found', 'type' => 'invalid_request', 'link' => null] : null,
        ];
    }

    /**
     * @param ObjectProphecy<Client> $client
     * @param \Closure(string): TasksResults $swapTasks returns the swap tasks for the given request id
     */
    private function createCommand(ObjectProphecy $client, \Closure $swapTasks): IndexCommand
    {
        $index = new Index('products', ProductDocument::class, [Product::class], new Container());

        $commandBus = $this->prophesize(MessageBusInterface::class);
        $commandBus->dispatch(Argument::type(IndexMessage::class))->will(function (array $args) use ($client, $swapTasks): Envelope {
            $message = $args[0];
            \assert($message instanceof IndexMessage);

            $client
                ->getTasks(Argument::that(static fn (TasksQuery $query): bool => 'indexSwap' === ($query->toArray()['types'] ?? null)))
                ->willReturn($swapTasks($message->requestId))
            ;

            return new Envelope($message);
        });

        $indexRegistry = $this->prophesize(IndexRegistryInterface::class);
        $indexRegistry->getNames()->willReturn(['products']);
        $indexRegistry->has('products')->willReturn(true);
        $indexRegistry->get('products')->willReturn($index);

        $indexUidsProvider = $this->prophesize(IndexUidsProviderInterface::class);
        $indexUidsProvider->get('products')->willReturn(['products__fashion_web__en_us__usd']);

        return new IndexCommand(
            $commandBus->reveal(),
            $indexRegistry->reveal(),
            $client->reveal(),
            $indexUidsProvider->reveal(),
        );
    }
}
