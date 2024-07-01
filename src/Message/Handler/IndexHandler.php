<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Message\Handler;

use Setono\SyliusMeilisearchPlugin\Config\IndexRegistry;
use Setono\SyliusMeilisearchPlugin\Exception\NonExistingIndexException;
use Setono\SyliusMeilisearchPlugin\Message\Command\Index;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

final class IndexHandler
{
    public function __construct(private readonly IndexRegistry $indexRegistry)
    {
    }

    public function __invoke(Index $message): void
    {
        try {
            $this->indexRegistry->get($message->index)->indexer->index($message->index);
        } catch (NonExistingIndexException $e) {
            throw new UnrecoverableMessageHandlingException(message: $e->getMessage(), previous: $e);
        }
    }
}
