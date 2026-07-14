<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\EventListener\Doctrine;

use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusMeilisearchPlugin\EventListener\Doctrine\EntityListener;
use Setono\SyliusMeilisearchPlugin\Message\Command\RemoveEntity;
use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
use Setono\SyliusMeilisearchPlugin\Tests\Unit\Indexer\SpyLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @covers \Setono\SyliusMeilisearchPlugin\EventListener\Doctrine\EntityListener
 */
final class EntityListenerTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_dispatches_a_remove_command_and_does_not_leak_storage_entries(): void
    {
        $entity = $this->prophesize(IndexableInterface::class);
        $entity->getId()->willReturn(42);
        $entity->getDocumentIdentifier()->willReturn('42');
        $revealedEntity = $entity->reveal();

        $eventArgs = new LifecycleEventArgs($revealedEntity, $this->prophesize(ObjectManager::class)->reveal());

        $commandBus = $this->prophesize(MessageBusInterface::class);
        // The document identifier is captured in preRemove and carried on the dispatched command.
        $commandBus->dispatch(Argument::that(static fn (RemoveEntity $command): bool => '42' === $command->documentIdentifier))
            ->willReturn(new Envelope(new \stdClass()))
            ->shouldBeCalledOnce();

        $listener = new EntityListener($commandBus->reveal());
        $listener->preRemove($eventArgs);
        $listener->postRemove($eventArgs);

        $storageProperty = new \ReflectionProperty(EntityListener::class, 'removeIndexableStorage');
        $storage = $storageProperty->getValue($listener);

        self::assertInstanceOf(\Countable::class, $storage);
        self::assertCount(0, $storage, 'The SplObjectStorage must not retain the entity after postRemove');
    }

    /**
     * @test
     */
    public function it_swallows_and_logs_a_failing_dispatch_so_the_entity_save_still_succeeds(): void
    {
        $entity = $this->prophesize(IndexableInterface::class);
        $entity->getId()->willReturn(7);
        $entity->getDocumentIdentifier()->willReturn('7');

        $eventArgs = new LifecycleEventArgs($entity->reveal(), $this->prophesize(ObjectManager::class)->reveal());

        $commandBus = $this->prophesize(MessageBusInterface::class);
        $commandBus->dispatch(Argument::any())->willThrow(new \RuntimeException('Meilisearch is down'));

        $logger = new SpyLogger();
        $listener = new EntityListener($commandBus->reveal(), $logger);

        // Must not throw, even though the dispatch fails
        $listener->postPersist($eventArgs);

        $error = $logger->firstOfLevel('error');
        self::assertNotNull($error);
    }
}
