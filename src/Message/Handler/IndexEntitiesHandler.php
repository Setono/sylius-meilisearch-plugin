<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Message\Handler;

use Doctrine\Persistence\ManagerRegistry;
use Meilisearch\Client;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\Config\IndexRegistryInterface;
use Setono\SyliusMeilisearchPlugin\Message\Command\IndexEntities;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

final class IndexEntitiesHandler
{
    use ORMTrait;

    public function __construct(
        ManagerRegistry $managerRegistry,
        private readonly IndexRegistryInterface $indexRegistry,
        private readonly Client $client,
    ) {
        $this->managerRegistry = $managerRegistry;
    }

    public function __invoke(IndexEntities $message): void
    {
        $entities = $this->getRepository($message->class)->findBy(['id' => $message->ids]);

        foreach ($this->resolveIndexes($message) as $index) {
            $index->indexer()->indexEntities($entities, $message->rebuild);
        }

        if (null !== $message->rebuild) {
            // Recorded only once every scope's documents have been handed to Meilisearch — even when
            // none of the entities exist anymore — because the finalization swaps when every batch
            // is recorded. The batch number is the document id, so a retried or redelivered batch
            // records itself idempotently.
            $this->client->index($message->rebuild->ledgerUid())->addDocuments([['id' => $message->batch]], 'id');
        }

        // Detach the batch's entities so a synchronous full reindex does not accumulate the whole
        // catalog in memory in one process.
        $this->getManager($message->class)->clear();
    }

    /**
     * @return iterable<Index>
     */
    private function resolveIndexes(IndexEntities $message): iterable
    {
        if (null === $message->index) {
            return $this->indexRegistry->getByEntity($message->class);
        }

        try {
            return [$this->indexRegistry->get($message->index)];
        } catch (\InvalidArgumentException $e) {
            throw new UnrecoverableMessageHandlingException(message: $e->getMessage(), previous: $e);
        }
    }
}
