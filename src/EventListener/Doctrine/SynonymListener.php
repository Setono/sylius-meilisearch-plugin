<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\EventListener\Doctrine;

use Doctrine\Persistence\Event\LifecycleEventArgs;
use Setono\SyliusMeilisearchPlugin\Message\Command\UpdateSynonyms;
use Setono\SyliusMeilisearchPlugin\Model\SynonymInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final class SynonymListener
{
    public function __construct(private readonly MessageBusInterface $commandBus)
    {
    }

    public function postPersist(LifecycleEventArgs $eventArgs): void
    {
        $this->handle($eventArgs);
    }

    public function postUpdate(LifecycleEventArgs $eventArgs): void
    {
        $this->handle($eventArgs);
    }

    public function postRemove(LifecycleEventArgs $eventArgs): void
    {
        $this->handle($eventArgs);
    }

    public function handle(LifecycleEventArgs $eventArgs): void
    {
        if (!$eventArgs->getObject() instanceof SynonymInterface) {
            return;
        }

        $this->commandBus->dispatch(new UpdateSynonyms());
    }
}
