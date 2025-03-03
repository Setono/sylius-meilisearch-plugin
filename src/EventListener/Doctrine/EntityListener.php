<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\EventListener\Doctrine;

use Doctrine\Persistence\Event\LifecycleEventArgs;
use Setono\SyliusMeilisearchPlugin\Message\Command\IndexEntity;
use Setono\SyliusMeilisearchPlugin\Message\Command\RemoveEntity;
use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
use SplObjectStorage;
use Sylius\Component\Resource\Model\TranslationInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final class EntityListener
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly SplObjectStorage $removeIndexableStorage = new SplObjectStorage(),
    ) {
    }

    public function postPersist(LifecycleEventArgs $eventArgs): void
    {
        $this->dispatch($eventArgs, static fn (IndexableInterface $entity) => IndexEntity::new($entity));
    }

    public function postUpdate(LifecycleEventArgs $eventArgs): void
    {
        $this->dispatch($eventArgs, static fn (IndexableInterface $entity) => IndexEntity::new($entity));
    }

    public function preRemove(LifecycleEventArgs $eventArgs): void
    {
        $indexable = $this->extractIndexableFromEvent($eventArgs);

        if ($indexable instanceof IndexableInterface) {
            $this->removeIndexableStorage->attach($indexable, $indexable->getId());
        }
    }

    public function postRemove(LifecycleEventArgs $eventArgs): void
    {
        $this->dispatch(
            $eventArgs,
            fn (IndexableInterface $entity) => new RemoveEntity($entity::class, $this->removeIndexableStorage[$entity]),
        );
    }

    /**
     * @param callable(IndexableInterface):object $message
     */
    private function dispatch(LifecycleEventArgs $eventArgs, callable $message): void
    {
        $indexable = $this->extractIndexableFromEvent($eventArgs);

        if ($indexable instanceof IndexableInterface) {
            $this->commandBus->dispatch($message($indexable));
        }
    }

    private function extractIndexableFromEvent(LifecycleEventArgs $eventArgs): ?IndexableInterface
    {
        $obj = $eventArgs->getObject();

        if ($obj instanceof TranslationInterface) {
            $obj = $obj->getTranslatable();
        }

        if (!$obj instanceof IndexableInterface) {
            return null;
        }

        return $obj;
    }
}
