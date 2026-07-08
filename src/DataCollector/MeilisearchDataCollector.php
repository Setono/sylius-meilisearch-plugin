<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\DataCollector;

use Meilisearch\Client;
use Meilisearch\Contracts\SearchQuery;
use Setono\SyliusMeilisearchPlugin\Meilisearch\Client\TraceableClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\VarDumper\Cloner\Data;
use Webmozart\Assert\Assert;

final class MeilisearchDataCollector extends DataCollector
{
    public function __construct(private readonly Client $client)
    {
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        if (!$this->client instanceof TraceableClient) {
            return;
        }

        $this->data['multiSearchRequests'] = [];

        foreach ($this->client->getMultiSearchRequests() as $multiSearchRequest) {
            /** @psalm-suppress MixedArrayAssignment */
            $this->data['multiSearchRequests'][] = [
                'queries' => array_map(fn (SearchQuery $query) => $this->cloneVar($query->toArray()), $multiSearchRequest['queries']),
                'results' => array_map($this->cloneVar(...), $multiSearchRequest['results']),
            ];
        }
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

    public function reset(): void
    {
        // Remove this when dropping support for SF5.4
        $this->data = [];
    }
}
