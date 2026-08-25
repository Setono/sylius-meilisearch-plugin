<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Message\Handler;

use Meilisearch\Client;
use Meilisearch\Exceptions\ApiException;
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

        /** @var list<string> $liveUids */
        $liveUids = [];

        foreach ($this->indexScopeProvider->getAll($index) as $indexScope) {
            $liveUid = $this->indexUidResolver->resolveFromIndexScope($indexScope);
            if (in_array($liveUid, $liveUids, true)) {
                continue;
            }
            $liveUids[] = $liveUid;

            $rebuildUid = RebuildUid::from($liveUid);

            // A rebuild index left over from a failed rebuild would leak its stale documents into
            // this rebuild through the upserts below, so start from a clean slate. The existence
            // check avoids enqueueing a deletion task that fails on every ordinary rebuild.
            if ($this->indexExists($rebuildUid)) {
                $this->client->deleteIndex($rebuildUid);
            }

            // Applying the settings creates the rebuild index as a side effect. The settings include
            // the synonyms, so the swapped-in index is complete without a separate synonym update.
            $this
                ->client
                ->index($rebuildUid)
                ->updateSettings(
                    $this->normalizer->normalize($this->settingsProvider->getSettings($indexScope)),
                )
            ;
        }

        if ([] === $liveUids) {
            return;
        }

        $index->indexer()->index();

        // Dispatched after the IndexEntities batches above so that, on the same (FIFO) transport,
        // it is handled only once every batch has been pushed to Meilisearch
        $this->commandBus->dispatch(new FinalizeIndexRebuild($index, $liveUids));
    }

    private function indexExists(string $uid): bool
    {
        try {
            $this->client->getRawIndex($uid);
        } catch (ApiException $e) {
            if (404 === $e->httpStatus) {
                return false;
            }

            throw $e;
        }

        return true;
    }
}
