<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Message\Handler;

use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\Message\Command\RemoveEntity;
use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;

final class RemoveEntityHandler extends AbstractEntityHandler
{
    public function __invoke(RemoveEntity $message): void
    {
        $this->handle($message, static fn (IndexableInterface $entity, Index $index) => $index->indexer()->removeEntity($entity));
    }
}
