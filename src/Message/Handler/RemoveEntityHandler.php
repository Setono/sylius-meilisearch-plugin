<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Message\Handler;

use Setono\SyliusMeilisearchPlugin\Config\IndexRegistryInterface;
use Setono\SyliusMeilisearchPlugin\Message\Command\RemoveEntity;

/**
 * Unlike the index handler, this handler must NOT reload the entity: a RemoveEntity message is
 * dispatched from postRemove, i.e. after the row has already been deleted, so a find() would always
 * return null. The message carries the document identifier captured before deletion, so the document
 * is removed by that identifier alone — no entity manager lookup required.
 */
final class RemoveEntityHandler
{
    public function __construct(private readonly IndexRegistryInterface $indexRegistry)
    {
    }

    public function __invoke(RemoveEntity $message): void
    {
        if (null === $message->documentIdentifier) {
            return;
        }

        foreach ($this->indexRegistry->getByEntity($message->class) as $index) {
            $index->indexer()->removeDocuments([$message->documentIdentifier]);
        }
    }
}
