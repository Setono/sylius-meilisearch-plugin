<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Message\Handler;

use Meilisearch\Client;
use Meilisearch\Exceptions\ApiException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Setono\SyliusMeilisearchPlugin\Config\IndexRegistryInterface;
use Setono\SyliusMeilisearchPlugin\Indexer\Rebuild;
use Setono\SyliusMeilisearchPlugin\Meilisearch\RebuildIndexes;
use Setono\SyliusMeilisearchPlugin\Message\Command\FinalizeIndexRebuild;
use Setono\SyliusMeilisearchPlugin\Message\Command\Index;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScopeProviderInterface;
use Setono\SyliusMeilisearchPlugin\Provider\Settings\SettingsProviderInterface;
use Setono\SyliusMeilisearchPlugin\Resolver\IndexUid\IndexUidResolverInterface;
use Setono\SyliusMeilisearchPlugin\Resolver\IndexUid\RebuildUid;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Webmozart\Assert\Assert;

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
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function __invoke(Index $message): void
    {
        try {
            $index = $this->indexRegistry->get($message->index);
        } catch (\InvalidArgumentException $e) {
            throw new UnrecoverableMessageHandlingException(message: $e->getMessage(), previous: $e);
        }

        $startedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $liveUids = [];
        $settings = [];

        foreach ($this->indexScopeProvider->getAll($index) as $indexScope) {
            $uid = $this->indexUidResolver->resolveFromIndexScope($indexScope);
            if (!in_array($uid, $liveUids, true)) {
                $liveUids[] = $uid;
            }

            $normalized = $this->normalizer->normalize($this->settingsProvider->getSettings($indexScope));
            Assert::isArray($normalized);

            // Every scope's settings are applied, even when scopes share a uid (the last one wins)
            $settings[] = [$uid, $normalized];
        }

        if ([] === $liveUids) {
            return;
        }

        $rebuild = new Rebuild(RebuildUid::generateId($message->requestId), $liveUids);

        $liveSettings = [];
        foreach ($settings as [$liveUid, $scopeSettings]) {
            $liveSettings[$liveUid] ??= $this->getUnmanagedLiveSettings($liveUid, $scopeSettings);

            // Applying the settings creates the rebuild index as a side effect. The settings include
            // the synonyms, so the swapped-in index is complete without a separate synonym update.
            $this->client->index($rebuild->uidOf($liveUid))->updateSettings(array_merge($liveSettings[$liveUid], $scopeSettings));
        }

        $this->deleteStaleRebuildIndexes($liveUids);

        $batches = $index->indexer()->index($rebuild);

        // Dispatched after the IndexEntities batches above. Transports do not guarantee ordering —
        // a transiently failed batch is redelivered later, behind this message — so the handler
        // does not rely on being handled last: it waits for every batch to be recorded in the run's
        // ledger (and for Meilisearch to have processed the run's tasks) before swapping.
        $this->commandBus->dispatch(new FinalizeIndexRebuild($index, $rebuild, $startedAt, $batches));
    }

    /**
     * The rebuild replaces the live index wholesale, so the live index's settings are carried over to
     * the rebuild index, and the plugin's settings are applied on top — just like the partial update
     * of the live index's settings did before rebuilds. This preserves settings the plugin does not
     * model (e.g. localizedAttributes or facetSearch configured in the Meilisearch dashboard).
     *
     * Embedders are the exception: Meilisearch redacts their API keys when settings are read, so a
     * copy would be broken. They have to be provided by the plugin's settings (see Settings).
     *
     * @param array<array-key, mixed> $settings the plugin's settings for the index
     *
     * @return array<array-key, mixed>
     */
    private function getUnmanagedLiveSettings(string $liveUid, array $settings): array
    {
        try {
            $liveSettings = $this->client->index($liveUid)->getSettings();
        } catch (ApiException $e) {
            // On the very first rebuild the live index does not exist yet
            if (404 === $e->httpStatus) {
                return [];
            }

            throw $e;
        }

        $embedders = $liveSettings['embedders'] ?? null;
        if (!isset($settings['embedders']) && is_countable($embedders) && count($embedders) > 0) {
            $this->logger->warning('The live index "{index}" has embedders that the plugin\'s settings do not provide. They are not carried over to the rebuilt index because Meilisearch redacts their API keys when settings are read; provide them through a settings provider instead.', [
                'index' => $liveUid,
            ]);
        }

        unset($liveSettings['embedders']);

        return $liveSettings;
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

        foreach (RebuildIndexes::all($this->client) as $uid) {
            foreach ($liveUids as $liveUid) {
                if (RebuildUid::isStale($uid, $liveUid, $now)) {
                    $this->client->deleteIndex($uid);

                    break;
                }
            }
        }
    }
}
