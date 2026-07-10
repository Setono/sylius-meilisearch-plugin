<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\DataCollector;

use Meilisearch\Client;
use Meilisearch\Contracts\SearchQuery;
use Setono\SyliusMeilisearchPlugin\Config\IndexRegistryInterface;
use Setono\SyliusMeilisearchPlugin\Meilisearch\Client\TraceableClient;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScopeProviderInterface;
use Setono\SyliusMeilisearchPlugin\Resolver\IndexUid\IndexUidResolverInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpKernel\DataCollector\LateDataCollectorInterface;
use Symfony\Component\VarDumper\Cloner\Data;
use Webmozart\Assert\Assert;

/**
 * @phpstan-type IndexData array{
 *     document: class-string,
 *     entities: list<class-string>,
 *     uids: array<string, array{numberOfDocuments: int|null, isIndexing: bool|null, fieldDistribution: Data}|null>,
 *     searchableAttributes: list<string>,
 *     filterableAttributes: list<string>,
 *     sortableAttributes: list<string>,
 *     facetableAttributes: list<string>,
 * }
 */
final class MeilisearchDataCollector extends DataCollector implements LateDataCollectorInterface
{
    public function __construct(
        private readonly Client $client,
        private readonly IndexRegistryInterface $indexRegistry,
        private readonly IndexScopeProviderInterface $indexScopeProvider,
        private readonly IndexUidResolverInterface $indexUidResolver,
    ) {
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        $this->data['multiSearchRequests'] = [];

        if ($this->client instanceof TraceableClient) {
            foreach ($this->client->getMultiSearchRequests() as $multiSearchRequest) {
                /** @psalm-suppress MixedArrayAssignment */
                $this->data['multiSearchRequests'][] = [
                    'queries' => array_map(fn (SearchQuery $query) => $this->cloneVar($query->toArray()), $multiSearchRequest['queries']),
                    'results' => array_map($this->cloneVar(...), $multiSearchRequest['results']),
                ];
            }
        }

        $indexes = [];

        foreach ($this->indexRegistry->getAll() as $index) {
            $uids = [];

            try {
                foreach ($this->indexScopeProvider->getAll($index) as $indexScope) {
                    $uids[$this->indexUidResolver->resolveFromIndexScope($indexScope)] = null;
                }
            } catch (\Throwable) {
                // enumerating index scopes queries the database, and the profiler should not break if that fails
            }

            $metadata = $index->metadata();

            $indexes[$index->name] = [
                'document' => $index->document,
                'entities' => $index->entities,
                'uids' => $uids,
                'searchableAttributes' => $metadata->getSearchableAttributeNames(),
                'filterableAttributes' => $metadata->getFilterableAttributeNames(),
                'sortableAttributes' => $metadata->getSortableAttributeNames(),
                'facetableAttributes' => $metadata->getFacetableAttributeNames(),
            ];
        }

        $this->data['indexes'] = $indexes;
    }

    public function lateCollect(): void
    {
        $indexes = $this->getIndexes();
        if ([] === $indexes) {
            return;
        }

        try {
            $stats = $this->client->stats();
        } catch (\Throwable $e) {
            $this->data['statsError'] = $e->getMessage();

            return;
        }

        $indexStats = $stats['indexes'] ?? [];
        if (!is_array($indexStats)) {
            return;
        }

        foreach ($indexes as $name => $index) {
            foreach (array_keys($index['uids']) as $uid) {
                $statsForUid = $indexStats[$uid] ?? null;
                if (!is_array($statsForUid)) {
                    continue;
                }

                $numberOfDocuments = $statsForUid['numberOfDocuments'] ?? null;
                $isIndexing = $statsForUid['isIndexing'] ?? null;

                $indexes[$name]['uids'][$uid] = [
                    'numberOfDocuments' => is_int($numberOfDocuments) ? $numberOfDocuments : null,
                    'isIndexing' => is_bool($isIndexing) ? $isIndexing : null,
                    'fieldDistribution' => $this->cloneVar($statsForUid['fieldDistribution'] ?? []),
                ];
            }
        }

        $this->data['indexes'] = $indexes;
    }

    public function getName(): string
    {
        return 'meilisearch';
    }

    /**
     * @return list<array{queries: list<Data>, results: array<Data>}>
     */
    public function getMultiSearchRequests(): array
    {
        /** @var list<array{queries: list<Data>, results: array<Data>}> $multiSearchRequests */
        $multiSearchRequests = $this->data['multiSearchRequests'] ?? [];
        Assert::isArray($multiSearchRequests);

        return $multiSearchRequests;
    }

    public function hasMultiSearchRequests(): bool
    {
        return [] !== $this->getMultiSearchRequests();
    }

    /**
     * @return array<string, IndexData>
     */
    public function getIndexes(): array
    {
        /** @var array<string, IndexData> $indexes */
        $indexes = $this->data['indexes'] ?? [];
        Assert::isArray($indexes);

        return $indexes;
    }

    public function getStatsError(): ?string
    {
        $statsError = $this->data['statsError'] ?? null;
        Assert::nullOrString($statsError);

        return $statsError;
    }

    public function reset(): void
    {
        // Remove this when dropping support for SF5.4
        $this->data = [];
    }
}
