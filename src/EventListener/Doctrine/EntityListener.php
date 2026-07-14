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
    /**
     * The id and document identifier are captured in preRemove — while the row still exists — because
     * postRemove runs after deletion, when the entity can no longer yield them reliably.
     *
     * @var SplObjectStorage<IndexableInterface, array{0: int|string|null, 1: string|null}>
     */
    private readonly SplObjectStorage $removeIndexableStorage;

    public function __construct(
        private readonly MessageBusInterface $commandBus,
    ) {
        $this->removeIndexableStorage = new SplObjectStorage();
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
        $indexable = self::extractIndexableFromEvent($eventArgs);

        if (null !== $indexable) {
            $this->removeIndexableStorage->attach($indexable, [$indexable->getId(), $indexable->getDocumentIdentifier()]);
        }
    }

    public function postRemove(LifecycleEventArgs $eventArgs): void
    {
        $indexable = self::extractIndexableFromEvent($eventArgs);

        if (null === $indexable) {
            return;
        }

        [$entityId, $documentIdentifier] = $this->removeIndexableStorage[$indexable] ?? [null, null];

        // Always detach so the SplObjectStorage does not grow unbounded in long-running workers
        // (it is attach-only in preRemove).
        $this->removeIndexableStorage->detach($indexable);

        // Without a document identifier there is nothing to remove from the index.
        if (null === $documentIdentifier) {
            return;
        }

        $this->dispatch(
            $eventArgs,
            fn (IndexableInterface $entity) => new RemoveEntity($entity::class, $entityId, $documentIdentifier),
        );
    }

    /**
     * @param callable(IndexableInterface):object $message
     */
    private function dispatch(LifecycleEventArgs $eventArgs, callable $message): void
    {
        $indexable = self::extractIndexableFromEvent($eventArgs);

        if (null !== $indexable) {
            $this->commandBus->dispatch($message($indexable));
        }
    }

    private static function extractIndexableFromEvent(LifecycleEventArgs $eventArgs): ?IndexableInterface
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
