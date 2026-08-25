<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Provider\IndexUids;

use Setono\SyliusMeilisearchPlugin\Config\IndexRegistryInterface;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScopeProviderInterface;
use Setono\SyliusMeilisearchPlugin\Resolver\IndexUid\IndexUidResolverInterface;

final class IndexUidsProvider implements IndexUidsProviderInterface
{
    public function __construct(
        private readonly IndexRegistryInterface $indexRegistry,
        private readonly IndexScopeProviderInterface $indexScopeProvider,
        private readonly IndexUidResolverInterface $indexUidResolver,
    ) {
    }

    public function get(string $index): array
    {
        $uids = [];

        foreach ($this->indexScopeProvider->getAll($this->indexRegistry->get($index)) as $indexScope) {
            $uid = $this->indexUidResolver->resolveFromIndexScope($indexScope);
            $uids[$uid] = $uid;
        }

        return array_values($uids);
    }

    public function getAll(): array
    {
        $result = [];

        foreach (array_keys($this->indexRegistry->getAll()) as $name) {
            $result[$name] = $this->get($name);
        }

        return $result;
    }
}
