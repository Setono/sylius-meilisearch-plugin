<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Indexer;

use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;

abstract class AbstractIndexer implements IndexerInterface
{
    public function indexEntity(IndexableInterface $entity): void
    {
        $this->indexEntities([$entity]);
    }

    public function removeEntity(IndexableInterface $entity): void
    {
        $this->removeEntities([$entity]);
    }
}
