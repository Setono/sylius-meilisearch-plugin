<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\Command;

use Meilisearch\Client;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusMeilisearchPlugin\Command\IndexCommand;
use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\Config\IndexRegistryInterface;
use Setono\SyliusMeilisearchPlugin\Document\Product as ProductDocument;
use Setono\SyliusMeilisearchPlugin\Message\Command\Index as IndexMessage;
use Setono\SyliusMeilisearchPlugin\Provider\IndexUids\IndexUidsProviderInterface;
use Setono\SyliusMeilisearchPlugin\Tests\Application\Entity\Product;
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
    public function it_scopes_the_tasks_query_to_the_given_index_uids(): void
    {
        $query = IndexCommand::createTasksQuery(['products__test', 'taxons__test']);

        $array = $query->toArray();

        self::assertSame('enqueued,processing', $array['statuses']);
        self::assertSame('products__test,taxons__test', $array['indexUids']);
    }

    /**
     * @test
     */
    public function it_does_not_scope_by_index_uid_when_no_uids_are_given(): void
    {
        $query = IndexCommand::createTasksQuery([]);

        $array = $query->toArray();

        self::assertSame('enqueued,processing', $array['statuses']);
        self::assertArrayNotHasKey('indexUids', $array);
    }

    /**
     * @test
     */
    public function it_prints_the_resolved_uids_and_an_honest_delete_warning(): void
    {
        $index = new Index('products', ProductDocument::class, [Product::class], new Container());

        $commandBus = $this->prophesize(MessageBusInterface::class);
        $commandBus->dispatch(Argument::type(IndexMessage::class))->willReturn(new Envelope(new \stdClass()));

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
        $tester->execute(['indexes' => ['products'], '--delete' => true]);

        $display = $tester->getDisplay();

        // Names each resolved index uid
        self::assertStringContainsString('products__fashion_web__en_us__usd', $display);
        // The --delete warning explains the downtime
        self::assertStringContainsString('search returns no results', $display);
    }
}
