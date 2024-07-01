<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Message\Handler;

use Setono\SyliusMeilisearchPlugin\Config\IndexRegistry;
use Setono\SyliusMeilisearchPlugin\Message\Command\IndexEntities;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

final class IndexEntitiesHandler
{
    private IndexRegistry $indexRegistry;

    public function __construct(IndexRegistry $indexRegistry)
    {
        $this->indexRegistry = $indexRegistry;
    }

    public function __invoke(IndexEntities $message): void
    {
        try {
            $this->indexRegistry
                ->getByResource($message->resource->class)
                ->indexer
                ->indexEntitiesWithIds($message->ids, $message->resource->class)
            ;
        } catch (\InvalidArgumentException $e) {
            throw new UnrecoverableMessageHandlingException($e->getMessage(), 0, $e);
        }
    }
}
