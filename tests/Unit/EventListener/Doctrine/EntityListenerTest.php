<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\EventListener\Doctrine;

use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusMeilisearchPlugin\EventListener\Doctrine\EntityListener;
use Setono\SyliusMeilisearchPlugin\Message\Command\IndexEntity;
use Setono\SyliusMeilisearchPlugin\Message\Command\RemoveEntity;
use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
use Setono\SyliusMeilisearchPlugin\Resolver\Indexable\ChannelPricingIndexableEntityResolver;
use Setono\SyliusMeilisearchPlugin\Resolver\Indexable\CompositeIndexableEntityResolver;
use Setono\SyliusMeilisearchPlugin\Resolver\Indexable\IndexableEntityResolver;
use Setono\SyliusMeilisearchPlugin\Tests\Unit\Indexer\SpyLogger;
use Sylius\Component\Core\Model\ChannelPricingInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
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

        $listener = new EntityListener($commandBus->reveal(), new IndexableEntityResolver());
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
        $listener = new EntityListener($commandBus->reveal(), new IndexableEntityResolver(), $logger);

        // Must not throw, even though the dispatch fails
        $listener->postPersist($eventArgs);

        $error = $logger->firstOfLevel('error');
        self::assertNotNull($error);
    }

    /**
     * @test
     */
    public function it_reindexes_the_owning_product_when_a_channel_price_changes(): void
    {
        $product = $this->prophesize(ProductInterface::class);
        $product->willImplement(IndexableInterface::class);
        $product->getId()->willReturn(11);
        $product->getDocumentIdentifier()->willReturn('11');

        $variant = $this->prophesize(ProductVariantInterface::class);
        $variant->getProduct()->willReturn($product->reveal());

        $channelPricing = $this->prophesize(ChannelPricingInterface::class);
        $channelPricing->getProductVariant()->willReturn($variant->reveal());

        $eventArgs = new LifecycleEventArgs($channelPricing->reveal(), $this->prophesize(ObjectManager::class)->reveal());

        /** @var list<IndexEntity> $dispatched */
        $dispatched = [];
        $commandBus = $this->prophesize(MessageBusInterface::class);
        $commandBus->dispatch(Argument::type(IndexEntity::class))->will(
            static function (array $args) use (&$dispatched): Envelope {
                /** @var IndexEntity $message */
                $message = $args[0];
                $dispatched[] = $message;

                return new Envelope($message);
            },
        );

        $composite = new CompositeIndexableEntityResolver();
        $composite->add(new IndexableEntityResolver());
        $composite->add(new ChannelPricingIndexableEntityResolver());

        $listener = new EntityListener($commandBus->reveal(), $composite);
        $listener->postUpdate($eventArgs);

        self::assertCount(1, $dispatched);
        self::assertSame(11, $dispatched[0]->id);
    }
}
