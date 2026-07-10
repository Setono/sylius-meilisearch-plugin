<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\EventListener\Doctrine;

use Doctrine\Persistence\Event\LifecycleEventArgs;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Setono\SyliusMeilisearchPlugin\Message\Command\IndexEntity;
use Setono\SyliusMeilisearchPlugin\Message\Command\RemoveEntity;
use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
use Setono\SyliusMeilisearchPlugin\Resolver\Indexable\IndexableEntityResolverInterface;
use SplObjectStorage;
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
        private readonly IndexableEntityResolverInterface $indexableEntityResolver,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
        $this->removeIndexableStorage = new SplObjectStorage();
    }

    public function postPersist(LifecycleEventArgs $eventArgs): void
    {
        $this->index($eventArgs->getObject());
    }

    public function postUpdate(LifecycleEventArgs $eventArgs): void
    {
        $this->index($eventArgs->getObject());
    }

    public function preRemove(LifecycleEventArgs $eventArgs): void
    {
        $object = $eventArgs->getObject();

        // Only the indexable entity itself needs its id and document identifier captured for a document
        // removal. They are captured here because they are no longer available once the row is deleted.
        if ($object instanceof IndexableInterface) {
            $this->removeIndexableStorage->attach($object, [$object->getId(), $object->getDocumentIdentifier()]);
        }
    }

    public function postRemove(LifecycleEventArgs $eventArgs): void
    {
        $object = $eventArgs->getObject();

        foreach ($this->indexableEntityResolver->resolve($object) as $indexable) {
            if ($object instanceof IndexableInterface && $indexable === $object) {
                // The indexable entity itself was removed: delete its document using the identifier
                // captured before deletion, so no reload of the (now gone) entity is needed.
                [$entityId, $documentIdentifier] = $this->removeIndexableStorage[$object] ?? [null, null];
                if (null !== $documentIdentifier) {
                    $this->dispatch(new RemoveEntity($object::class, $entityId, $documentIdentifier));
                }

                continue;
            }

            // A related entity was removed (e.g. a channel price or a variant): reindex the owner
            $this->dispatch(IndexEntity::new($indexable));
        }

        if ($object instanceof IndexableInterface) {
            // Always detach so the SplObjectStorage does not grow unbounded in long-running workers
            $this->removeIndexableStorage->detach($object);
        }
    }

    private function index(object $object): void
    {
        foreach ($this->indexableEntityResolver->resolve($object) as $indexable) {
            $this->dispatch(IndexEntity::new($indexable));
        }
    }

    private function dispatch(object $message): void
    {
        try {
            $this->commandBus->dispatch($message);
        } catch (\Throwable $e) {
            // A search-index update (or its transport being unavailable) must never break the
            // entity save it is reacting to. Swallow and log instead.
            $this->logger->error('Failed to dispatch a Meilisearch indexing command for an entity change: {message}', [
                'message' => $e->getMessage(),
                'command' => $message::class,
                'exception' => $e,
            ]);
        }
    }
}
