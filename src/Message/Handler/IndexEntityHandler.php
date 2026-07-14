<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Message\Handler;

use Doctrine\Persistence\ManagerRegistry;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusMeilisearchPlugin\Config\IndexRegistryInterface;
use Setono\SyliusMeilisearchPlugin\Message\Command\IndexEntity;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

final class IndexEntityHandler
{
    use ORMTrait;

    public function __construct(ManagerRegistry $managerRegistry, private readonly IndexRegistryInterface $indexRegistry)
    {
        $this->managerRegistry = $managerRegistry;
    }

    public function __invoke(IndexEntity $message): void
    {
        $id = $message->id;
        if (!is_int($id) && !is_string($id)) {
            throw new UnrecoverableMessageHandlingException(sprintf('The id of entity %s must be an int or a string', $message->class));
        }

        $entity = $this->getManager($message->class)->find($message->class, $id);
        if (null === $entity) {
            throw new UnrecoverableMessageHandlingException(sprintf('Entity (%s) with id %s not found', $message->class, (string) $id));
        }

        foreach ($this->indexRegistry->getByEntity($message->class) as $index) {
            // Ask the same data provider a full reindex uses whether this entity still qualifies for the
            // index. This keeps the incremental path in sync with the batch path: the query-builder
            // filters are the single source of truth. If it no longer qualifies (e.g. it was disabled or
            // went out of stock), remove its document instead of leaving a stale search hit.
            if ($index->dataProvider()->containsId($message->class, $index, $id)) {
                $index->indexer()->indexEntity($entity);
            } else {
                $index->indexer()->removeEntity($entity);
            }
        }
    }
}
