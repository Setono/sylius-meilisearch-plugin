<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Message\Handler;

use Setono\SyliusMeilisearchPlugin\Config\IndexRegistryInterface;
use Setono\SyliusMeilisearchPlugin\Exception\NonExistingIndexException;
use Setono\SyliusMeilisearchPlugin\Message\Command\Index;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

final class IndexHandler
{
    public function __construct(private readonly IndexRegistryInterface $indexRegistry)
    {
    }

    public function __invoke(Index $message): void
    {
        try {
            $this->indexRegistry->get($message->index)->indexer()->index();
        } catch (NonExistingIndexException $e) {
            throw new UnrecoverableMessageHandlingException(message: $e->getMessage(), previous: $e);
        }
    }
}
