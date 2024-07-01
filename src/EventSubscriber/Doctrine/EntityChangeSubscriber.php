<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\EventSubscriber\Doctrine;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Setono\SyliusMeilisearchPlugin\Message\Command\IndexEntity;
use Setono\SyliusMeilisearchPlugin\Message\Command\RemoveEntity;
use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final class EntityChangeSubscriber implements EventSubscriber
{
    public function __construct(private readonly MessageBusInterface $commandBus)
    {
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::postPersist => 'update',
            Events::postUpdate => 'update',
            Events::postRemove => 'remove',
        ];
    }

    public function update(LifecycleEventArgs $eventArgs): void
    {
        $this->dispatch($eventArgs, static fn (IndexableInterface $entity) => IndexEntity::new($entity));
    }

    public function remove(LifecycleEventArgs $eventArgs): void
    {
        $this->dispatch($eventArgs, static fn (IndexableInterface $entity) => RemoveEntity::new($entity));
    }

    /**
     * @param callable(IndexableInterface):object $message
     */
    private function dispatch(LifecycleEventArgs $eventArgs, callable $message): void
    {
        $obj = $eventArgs->getObject();
        if (!$obj instanceof IndexableInterface) {
            return;
        }

        $this->commandBus->dispatch($message($obj));
    }
}
