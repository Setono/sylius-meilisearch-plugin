<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Message\Handler;

use Meilisearch\Client;
use Meilisearch\Contracts\IndexesQuery;
use Setono\SyliusMeilisearchPlugin\Config\IndexRegistryInterface;
use Setono\SyliusMeilisearchPlugin\Message\Command\FinalizeIndexRebuild;
use Setono\SyliusMeilisearchPlugin\Message\Command\Index;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScopeProviderInterface;
use Setono\SyliusMeilisearchPlugin\Provider\Settings\SettingsProviderInterface;
use Setono\SyliusMeilisearchPlugin\Resolver\IndexUid\IndexUidResolverInterface;
use Setono\SyliusMeilisearchPlugin\Resolver\IndexUid\RebuildUid;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class IndexHandler
{
    private const INDEX_PAGE_SIZE = 200;

    public function __construct(
        private readonly IndexRegistryInterface $indexRegistry,
        private readonly Client $client,
        private readonly SettingsProviderInterface $settingsProvider,
        private readonly IndexScopeProviderInterface $indexScopeProvider,
        private readonly IndexUidResolverInterface $indexUidResolver,
        private readonly NormalizerInterface $normalizer,
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(Index $message): void
    {
        try {
            $index = $this->indexRegistry->get($message->index);
        } catch (\InvalidArgumentException $e) {
            throw new UnrecoverableMessageHandlingException(message: $e->getMessage(), previous: $e);
        }

        $rebuildId = RebuildUid::generateId();

        /** @var list<string> $liveUids */
        $liveUids = [];

        foreach ($this->indexScopeProvider->getAll($index) as $indexScope) {
            $liveUid = $this->indexUidResolver->resolveFromIndexScope($indexScope);
            if (in_array($liveUid, $liveUids, true)) {
                continue;
            }
            $liveUids[] = $liveUid;

            // Applying the settings creates the rebuild index as a side effect. The settings include
            // the synonyms, so the swapped-in index is complete without a separate synonym update.
            $this
                ->client
                ->index(RebuildUid::from($liveUid, $rebuildId))
                ->updateSettings(
                    $this->normalizer->normalize($this->settingsProvider->getSettings($indexScope)),
                )
            ;
        }

        if ([] === $liveUids) {
            return;
        }

        $this->deleteStaleRebuildIndexes($liveUids);

        $index->indexer()->index($rebuildId);

        // Dispatched after the IndexEntities batches above so that, on the same (FIFO) transport,
        // it is handled only once every batch has been pushed to Meilisearch
        $this->commandBus->dispatch(new FinalizeIndexRebuild($index, $liveUids, $rebuildId));
    }

    /**
     * Deletes rebuild indexes left behind by crashed rebuilds. Every rebuild run builds into its own
     * uids, so an overlapping run must never delete indexes another run may still be using — only
     * rebuild indexes whose embedded timestamp is old enough that no rebuild can still be running
     * are removed.
     *
     * @param list<string> $liveUids
     */
    private function deleteStaleRebuildIndexes(array $liveUids): void
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $uids = [];
        $offset = 0;
        do {
            $results = $this->client->getIndexes(
                (new IndexesQuery())->setOffset($offset)->setLimit(self::INDEX_PAGE_SIZE),
            );

            foreach ($results->getResults() as $meilisearchIndex) {
                $uid = $meilisearchIndex->getUid();
                if (null !== $uid) {
                    $uids[] = $uid;
                }
            }

            $offset += self::INDEX_PAGE_SIZE;
        } while ($offset < $results->getTotal());

        foreach ($uids as $uid) {
            foreach ($liveUids as $liveUid) {
                if (RebuildUid::isStale($uid, $liveUid, $now)) {
                    $this->client->deleteIndex($uid);

                    break;
                }
            }
        }
    }
}
