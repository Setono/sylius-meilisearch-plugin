<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Javascript\Autocomplete;

use Setono\SyliusMeilisearchPlugin\Config\IndexRegistryInterface;
use Setono\SyliusMeilisearchPlugin\Resolver\IndexName\IndexNameResolverInterface;

final class SourcesResolver implements SourcesResolverInterface
{
    /**
     * @param list<string> $searchIndexes
     */
    public function __construct(
        private readonly IndexRegistryInterface $indexRegistry,
        private readonly IndexNameResolverInterface $indexNameResolver,
        /**
         * These are the search indexes defined in the configuration of the plugin (inside setono_sylius_meilisearch.search.indexes)
         */
        private readonly array $searchIndexes,
    ) {
    }

    public function getSources(): array
    {
        $sources = [];
        foreach ($this->searchIndexes as $searchIndex) {
            $index = $this->indexRegistry->get($searchIndex);
            $sources[] = new Source($searchIndex, $this->indexNameResolver->resolve($index));
        }

        return $sources;
    }
}
