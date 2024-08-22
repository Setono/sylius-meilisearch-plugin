<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\EventListener\Doctrine;

use Doctrine\Persistence\Event\LifecycleEventArgs;
use Setono\SyliusMeilisearchPlugin\Message\Command\UpdateSynonyms;
use Setono\SyliusMeilisearchPlugin\Model\SynonymInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\MessageBusInterface;

final class SynonymListener implements EventSubscriberInterface
{
    private bool $update = false;

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

    /**
     * This method can be called multiple times in the same request, therefore we set a flag to only dispatch the command once
     */
    public function handle(LifecycleEventArgs $eventArgs): void
    {
        if (!$eventArgs->getObject() instanceof SynonymInterface) {
            return;
        }

        $this->update = true;
    }

    public function dispatch(): void
    {
        if (!$this->update) {
            return;
        }

        $this->commandBus->dispatch(new UpdateSynonyms());
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['dispatch', 10],
        ];
    }
}
