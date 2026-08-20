<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\EventListener\Doctrine;

use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Setono\SyliusMeilisearchPlugin\Config\IndexRegistryInterface;
use Setono\SyliusMeilisearchPlugin\Message\Command\Index;
use Setono\SyliusMeilisearchPlugin\Model\IndexableAttributeInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * When the admin changes the indexable attribute configuration, the affected indexes must have their
 * settings updated AND their documents reindexed (else the settings and the indexed documents desync),
 * which is exactly what the Index command does
 */
final class IndexableAttributeListener implements EventSubscriberInterface
{
    /** @var array<string, true> */
    private array $affectedIndexes = [];

    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly IndexRegistryInterface $indexRegistry,
        private readonly ResetInterface $metadataFactory,
    ) {
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
     * When an index is removed from a row, that index must also be reindexed so its settings and
     * documents shrink accordingly - the post events only see the new value, so collect the old one here
     */
    public function preUpdate(PreUpdateEventArgs $eventArgs): void
    {
        if (!$eventArgs->getObject() instanceof IndexableAttributeInterface) {
            return;
        }

        if (!$eventArgs->hasChangedField('indexes')) {
            return;
        }

        /** @var mixed $oldIndexes */
        $oldIndexes = $eventArgs->getOldValue('indexes');
        if (!is_array($oldIndexes)) {
            return;
        }

        foreach ($oldIndexes as $index) {
            if (is_string($index)) {
                $this->affectedIndexes[$index] = true;
            }
        }
    }

    private function handle(LifecycleEventArgs $eventArgs): void
    {
        $object = $eventArgs->getObject();
        if (!$object instanceof IndexableAttributeInterface) {
            return;
        }

        foreach ($object->getIndexes() as $index) {
            $this->affectedIndexes[$index] = true;
        }
    }

    /**
     * The Doctrine events can fire multiple times in the same request, therefore the affected
     * indexes are collected and a single Index command per affected index is dispatched here
     */
    public function dispatch(): void
    {
        if ([] === $this->affectedIndexes) {
            return;
        }

        // protect against index names that are no longer configured (e.g. old rows)
        $indexes = array_intersect(array_keys($this->affectedIndexes), $this->indexRegistry->getNames());
        $this->affectedIndexes = [];

        if ([] === $indexes) {
            return;
        }

        // the metadata may already have been memoized in this process before the configuration was saved
        $this->metadataFactory->reset();

        foreach ($indexes as $index) {
            $this->commandBus->dispatch(new Index($index));
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['dispatch', 10],
        ];
    }
}
