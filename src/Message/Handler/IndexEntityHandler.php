<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Message\Handler;

use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\Message\Command\IndexEntity;
use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;

final class IndexEntityHandler extends AbstractEntityHandler
{
    public function __invoke(IndexEntity $message): void
    {
        $this->handle($message, static fn (IndexableInterface $entity, Index $index) => $index->indexer()->indexEntity($entity));
    }
}
