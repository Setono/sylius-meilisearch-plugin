<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Javascript\Autocomplete;

use Setono\SyliusMeilisearchPlugin\Config\IndexRegistryInterface;
use Setono\SyliusMeilisearchPlugin\Resolver\IndexName\IndexNameResolverInterface;

final class SourcesResolver implements SourcesResolverInterface
{
    public function __construct(
        private readonly IndexRegistryInterface $indexRegistry,
        private readonly IndexNameResolverInterface $indexNameResolver,
        /**
         * This is the search index defined in the configuration of the plugin (inside setono_sylius_meilisearch.search.index)
         */
        private readonly string $searchIndex,
    ) {
    }

    public function getSources(): array
    {
        $index = $this->indexRegistry->get($this->searchIndex);

        return [new Source($this->searchIndex, $this->indexNameResolver->resolve($index))];
    }
}
