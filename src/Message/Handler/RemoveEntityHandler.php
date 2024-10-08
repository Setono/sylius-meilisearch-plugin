<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Message\Handler;

use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;

final class RemoveEntityHandler extends AbstractEntityHandler
{
    protected function execute(IndexableInterface $entity, Index $index): void
    {
        $index->indexer()->removeEntity($entity);
    }
}
