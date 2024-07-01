<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Message\Handler;

use Setono\SyliusMeilisearchPlugin\Config\IndexRegistryInterface;
use Setono\SyliusMeilisearchPlugin\Message\Command\IndexEntity;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

final class IndexEntityHandler
{
    public function __construct(private readonly IndexRegistryInterface $indexRegistry)
    {
    }

    public function __invoke(IndexEntity $message): void
    {
        try {
            $this->indexRegistry
                ->getByResource($message->class)
                ->indexer
                ->indexEntityWithId($message->id, $message->class)
            ;
        } catch (\InvalidArgumentException $e) {
            // todo create better exception
            throw new UnrecoverableMessageHandlingException($e->getMessage(), 0, $e);
        }
    }
}
