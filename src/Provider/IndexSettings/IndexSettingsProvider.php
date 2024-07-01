<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Provider\IndexSettings;

use Setono\SyliusMeilisearchPlugin\IndexScope\IndexScope;
use Setono\SyliusMeilisearchPlugin\Resolver\IndexName\IndexNameResolverInterface;
use Setono\SyliusMeilisearchPlugin\Resolver\ReplicaIndexName\ReplicaIndexNameResolverInterface;
use Setono\SyliusMeilisearchPlugin\Settings\IndexSettings;
use Setono\SyliusMeilisearchPlugin\Settings\SortableReplica;

final class IndexSettingsProvider implements IndexSettingsProviderInterface
{
    public function __construct(
        private readonly IndexNameResolverInterface $indexNameResolver,
        private readonly ReplicaIndexNameResolverInterface $replicaIndexNameResolver,
    )
    {
    }

    // todo implement an easier way to set settings decoupled from the document. This could be by dispatching an IndexSettingsEvent
    public function getSettings(IndexScope $indexScope): IndexSettings
    {
        $settings = $indexScope->index->document::getDefaultSettings($indexScope);
        $indexName = $this->indexNameResolver->resolveFromIndexScope($indexScope);

        foreach ($settings->replicas as &$replica) {
            $replica = $this->replicaIndexNameResolver->resolveFromIndexNameAndExistingValue($indexName, (string) $replica);
        }
        unset($replica);

        foreach ($indexScope->index->document::getSortableAttributes() as $attribute => $order) {
            $settings->replicas[] = new SortableReplica(
                $this->replicaIndexNameResolver->resolveFromIndexNameAndSortableAttribute($indexName, $attribute, $order),
                $attribute,
                $order,
            );
        }

        return $settings;
    }
}
