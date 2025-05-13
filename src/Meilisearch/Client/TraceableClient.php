<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Meilisearch\Client;

use Meilisearch\Client;
use Meilisearch\Contracts\MultiSearchFederation;
use Meilisearch\Contracts\SearchQuery;
use Webmozart\Assert\Assert;

final class TraceableClient extends Client
{
    /** @var list<array{queries: list<SearchQuery>, results: array}> */
    private array $multiSearchRequests = [];

    public function multiSearch(array $queries = [], ?MultiSearchFederation $federation = null): array
    {
        $results = parent::multiSearch($queries, $federation);
        Assert::isArray($results);

        $this->multiSearchRequests[] = [
            'queries' => $queries,
            'results' => isset($results['results']) && is_array($results['results']) ? $results['results'] : [],
        ];

        return $results;
    }

    /**
     * @return list<array{queries: list<SearchQuery>, results: array}>
     */
    public function getMultiSearchRequests(): array
    {
        return $this->multiSearchRequests;
    }
}
