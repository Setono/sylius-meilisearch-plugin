<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\EventListener\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusMeilisearchPlugin\Config\IndexRegistryInterface;
use Setono\SyliusMeilisearchPlugin\EventListener\Doctrine\IndexableAttributeListener;
use Setono\SyliusMeilisearchPlugin\Message\Command\Index;
use Setono\SyliusMeilisearchPlugin\Model\IndexableAttribute;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @covers \Setono\SyliusMeilisearchPlugin\EventListener\Doctrine\IndexableAttributeListener
 */
final class IndexableAttributeListenerTest extends TestCase
{
    use ProphecyTrait;

    /** @var list<string> */
    private array $dispatchedIndexes = [];

    private bool $metadataFactoryWasReset = false;

    /**
     * @test
     */
    public function it_dispatches_one_index_command_per_affected_index(): void
    {
        $listener = $this->listener();

        $listener->postPersist($this->lifecycleEvent(self::row(['products'])));
        // the same index a second time must not lead to a second dispatch
        $listener->postUpdate($this->lifecycleEvent(self::row(['products', 'taxons'])));
        // an index that is not configured (anymore) is filtered out
        $listener->postRemove($this->lifecycleEvent(self::row(['ghost'])));

        $listener->dispatch();

        self::assertSame(['products', 'taxons'], $this->dispatchedIndexes);
        self::assertTrue($this->metadataFactoryWasReset);

        // the collected indexes are cleared after dispatching
        $listener->dispatch();
        self::assertSame(['products', 'taxons'], $this->dispatchedIndexes);
    }

    /**
     * @test
     */
    public function it_also_reindexes_indexes_that_were_removed_from_a_row(): void
    {
        $listener = $this->listener();

        $row = self::row(['products']);

        $entityManager = $this->prophesize(EntityManagerInterface::class);
        $changeSet = ['indexes' => [['products', 'taxons'], ['products']]];

        $listener->preUpdate(new PreUpdateEventArgs($row, $entityManager->reveal(), $changeSet));
        $listener->postUpdate($this->lifecycleEvent($row));

        $listener->dispatch();

        self::assertSame(['products', 'taxons'], $this->dispatchedIndexes);
    }

    /**
     * @test
     */
    public function it_does_not_dispatch_when_nothing_changed(): void
    {
        $listener = $this->listener();

        $listener->postPersist($this->lifecycleEvent(new \stdClass()));
        $listener->dispatch();

        self::assertSame([], $this->dispatchedIndexes);
        self::assertFalse($this->metadataFactoryWasReset);
    }

    private function listener(): IndexableAttributeListener
    {
        $this->dispatchedIndexes = [];
        $this->metadataFactoryWasReset = false;

        $dispatchedIndexes = &$this->dispatchedIndexes;
        $metadataFactoryWasReset = &$this->metadataFactoryWasReset;

        $commandBus = $this->prophesize(MessageBusInterface::class);
        $commandBus->dispatch(Argument::type(Index::class))->will(static function (array $args) use (&$dispatchedIndexes): Envelope {
            /** @var Index $message */
            $message = $args[0];
            $dispatchedIndexes[] = $message->index;

            return new Envelope($message);
        });

        $indexRegistry = $this->prophesize(IndexRegistryInterface::class);
        $indexRegistry->getNames()->willReturn(['products', 'taxons']);

        $metadataFactory = $this->prophesize(ResetInterface::class);
        $metadataFactory->reset()->will(static function () use (&$metadataFactoryWasReset): void {
            $metadataFactoryWasReset = true;
        });

        return new IndexableAttributeListener(
            $commandBus->reveal(),
            $indexRegistry->reveal(),
            $metadataFactory->reveal(),
        );
    }

    /**
     * @param list<string> $indexes
     */
    private static function row(array $indexes): IndexableAttribute
    {
        $row = new IndexableAttribute();
        $row->setType(IndexableAttribute::TYPE_ATTRIBUTE);
        $row->setCode('color');
        foreach ($indexes as $index) {
            $row->addIndex($index);
        }

        return $row;
    }

    /**
     * @return LifecycleEventArgs<ObjectManager>
     */
    private function lifecycleEvent(object $entity): LifecycleEventArgs
    {
        return new LifecycleEventArgs($entity, $this->prophesize(ObjectManager::class)->reveal());
    }
}
