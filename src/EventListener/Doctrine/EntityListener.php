<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\EventListener\Doctrine;

use Doctrine\Persistence\Event\LifecycleEventArgs;
use Setono\SyliusMeilisearchPlugin\Message\Command\IndexEntity;
use Setono\SyliusMeilisearchPlugin\Message\Command\RemoveEntity;
use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
use Sylius\Component\Resource\Model\TranslationInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final class EntityListener
{
    public function __construct(private readonly MessageBusInterface $commandBus)
    {
    }

    public function postPersist(LifecycleEventArgs $eventArgs): void
    {
        $this->dispatch($eventArgs, static fn (IndexableInterface $entity) => IndexEntity::new($entity));
    }

    public function postUpdate(LifecycleEventArgs $eventArgs): void
    {
        $this->dispatch($eventArgs, static fn (IndexableInterface $entity) => IndexEntity::new($entity));
    }

    public function postRemove(LifecycleEventArgs $eventArgs): void
    {
        $this->dispatch($eventArgs, static fn (IndexableInterface $entity) => RemoveEntity::new($entity));
    }

    /**
     * @param callable(IndexableInterface):object $message
     */
    private function dispatch(LifecycleEventArgs $eventArgs, callable $message): void
    {
        $obj = $eventArgs->getObject();

        if ($obj instanceof TranslationInterface) {
            $obj = $obj->getTranslatable();
        }

        if (!$obj instanceof IndexableInterface) {
            return;
        }

        $this->commandBus->dispatch($message($obj));
    }
}
