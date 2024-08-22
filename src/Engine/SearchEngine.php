<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Engine;

use Meilisearch\Client;
use Meilisearch\Search\SearchResult;
use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\Facet;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\MetadataFactoryInterface;
use Setono\SyliusMeilisearchPlugin\Meilisearch\Builder\FilterBuilderInterface;
use Setono\SyliusMeilisearchPlugin\Resolver\IndexName\IndexNameResolverInterface;

final class SearchEngine implements SearchEngineInterface
{
    public function __construct(
        private readonly MetadataFactoryInterface $metadataFactory,
        private readonly FilterBuilderInterface $filterBuilder,
        private readonly Index $index,
        private readonly IndexNameResolverInterface $indexNameResolver,
        private readonly Client $client,
        private readonly int $hitsPerPage,
    ) {
    }

    public function execute(?string $query, array $parameters = []): SearchResult
    {
        $page = max(1, (int) ($parameters['p'] ?? 1));
        $sort = (string) ($parameters['sort'] ?? '');

        $metadata = $this->metadataFactory->getMetadataFor($this->index->document);

        $searchParams = [
            'facets' => array_map(static fn (Facet $facet) => $facet->name, $metadata->getFacets()),
            'filter' => $this->filterBuilder->build($parameters),
            'hitsPerPage' => $this->hitsPerPage,
            'page' => $page,
        ];
        if ('' !== $sort) {
            $searchParams['sort'] = [$sort];
        }

        return $this->client->index($this->indexNameResolver->resolve($this->index))->search($query, $searchParams);
    }
}
