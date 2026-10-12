<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Message\Handler;

use Meilisearch\Client;
use Setono\SyliusMeilisearchPlugin\Config\IndexRegistryInterface;
use Setono\SyliusMeilisearchPlugin\Meilisearch\RebuildIndexes;
use Setono\SyliusMeilisearchPlugin\Meilisearch\SynonymResolverInterface;
use Setono\SyliusMeilisearchPlugin\Message\Command\UpdateSynonyms;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScopeProviderInterface;
use Setono\SyliusMeilisearchPlugin\Resolver\IndexUid\IndexUidResolverInterface;

final class UpdateSynonymsHandler
{
    public function __construct(
        private readonly Client $client,
        private readonly IndexRegistryInterface $indexRegistry,
        private readonly IndexScopeProviderInterface $indexScopeProvider,
        private readonly IndexUidResolverInterface $indexNameResolver,
        private readonly SynonymResolverInterface $synonymResolver,
    ) {
    }

    public function __invoke(UpdateSynonyms $message): void
    {
        $scopes = [];
        foreach ($this->indexRegistry as $index) {
            foreach ($this->indexScopeProvider->getAll($index) as $indexScope) {
                $scopes[] = [$indexScope, $this->indexNameResolver->resolveFromIndexScope($indexScope)];
            }
        }

        // A rebuild in flight applied the synonyms when it started, and its swap would revert this
        // update — so the synonyms are pushed to its rebuild indexes too
        $inFlight = RebuildIndexes::inFlight($this->client, array_column($scopes, 1));

        foreach ($scopes as [$indexScope, $liveUid]) {
            $synonyms = $this->synonymResolver->resolve($indexScope);

            foreach ([$liveUid, ...$inFlight[$liveUid] ?? []] as $uid) {
                $this->client->index($uid)->updateSynonyms($synonyms);
            }
        }
    }
}
