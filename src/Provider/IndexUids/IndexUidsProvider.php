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

    public function getAll(): array
    {
        $result = [];

        foreach ($this->indexRegistry->getAll() as $name => $index) {
            $uids = [];

            foreach ($this->indexScopeProvider->getAll($index) as $indexScope) {
                $uid = $this->indexUidResolver->resolveFromIndexScope($indexScope);
                $uids[$uid] = $uid;
            }

            $result[$name] = array_values($uids);
        }

        return $result;
    }

    public function getFlattened(): array
    {
        $uids = [];

        foreach ($this->getAll() as $uidsForIndex) {
            foreach ($uidsForIndex as $uid) {
                $uids[$uid] = $uid;
            }
        }

        return array_values($uids);
    }
}
