<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Message\Handler;

use Meilisearch\Client;
use Setono\SyliusMeilisearchPlugin\Config\IndexRegistryInterface;
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
        foreach ($this->indexRegistry as $index) {
            foreach ($this->indexScopeProvider->getAll($index) as $indexScope) {
                $this
                    ->client
                    ->index($this->indexNameResolver->resolveFromIndexScope($indexScope))
                    ->updateSynonyms($this->synonymResolver->resolve($indexScope))
                ;
            }
        }
    }
}
