<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Provider\Settings;

use Setono\SyliusMeilisearchPlugin\Document\Metadata\MetadataFactoryInterface;
use Setono\SyliusMeilisearchPlugin\Meilisearch\SynonymResolverInterface;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScope;
use Setono\SyliusMeilisearchPlugin\Settings\Settings;

final class SettingsProvider implements SettingsProviderInterface
{
    public function __construct(
        private readonly SynonymResolverInterface $synonymResolver,
        private readonly MetadataFactoryInterface $metadataFactory,
    ) {
    }

    public function getSettings(IndexScope $indexScope): Settings
    {
        $settings = new Settings();

        $metadata = $this->metadataFactory->getMetadataFor($indexScope->index->document);

        $settings->filterableAttributes->add(...$metadata->getFilterableAttributeNames());
        $settings->searchableAttributes->add(...$metadata->getSearchableAttributeNames());
        $settings->sortableAttributes->add(...$metadata->getSortableAttributeNames());

        $settings->synonyms = $this->synonymResolver->resolve($indexScope);

        return $settings;
    }
}
