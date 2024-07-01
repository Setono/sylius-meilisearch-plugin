<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Message\Handler;

use Setono\SyliusMeilisearchPlugin\Config\IndexRegistry;
use Setono\SyliusMeilisearchPlugin\Exception\NonExistingIndexException;
use Setono\SyliusMeilisearchPlugin\Exception\NonExistingResourceException;
use Setono\SyliusMeilisearchPlugin\Message\Command\IndexResource;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

final class IndexResourceHandler
{
    private IndexRegistry $indexRegistry;

    public function __construct(IndexRegistry $indexRegistry)
    {
        $this->indexRegistry = $indexRegistry;
    }

    public function __invoke(IndexResource $message): void
    {
        try {
            $this->indexRegistry
                ->get($message->index)
                ->indexer
                ->indexResource($message->index, $message->resource)
            ;
        } catch (NonExistingResourceException|NonExistingIndexException $e) {
            throw new UnrecoverableMessageHandlingException($e->getMessage(), 0, $e);
        }
    }
}
