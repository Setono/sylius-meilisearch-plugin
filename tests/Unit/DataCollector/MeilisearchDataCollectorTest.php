<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\DataCollector;

use Meilisearch\Client;
use Meilisearch\Contracts\SearchQuery;
use Nyholm\Psr7\Response as HttpResponse;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\Config\IndexRegistry;
use Setono\SyliusMeilisearchPlugin\DataCollector\MeilisearchDataCollector;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\MetadataFactory;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\MetadataFactoryInterface;
use Setono\SyliusMeilisearchPlugin\Meilisearch\Client\TraceableClient;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScope;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScopeProviderInterface;
use Setono\SyliusMeilisearchPlugin\Resolver\IndexUid\IndexUidResolverInterface;
use Setono\SyliusMeilisearchPlugin\Tests\Unit\Document\Metadata\Document;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\VarDumper\Cloner\Data;

final class MeilisearchDataCollectorTest extends TestCase
{
    use ProphecyTrait;

    private const UID = 'test__products__fashion_web__en_us__usd';

    /**
     * @test
     */
    public function it_collects_index_configuration(): void
    {
        $collector = $this->createCollector($this->prophesize(Client::class)->reveal());
        $collector->collect(new Request(), new Response());

        self::assertFalse($collector->hasMultiSearchRequests());
        self::assertNull($collector->getStatsError());

        $indexes = $collector->getIndexes();
        self::assertArrayHasKey('products', $indexes);

        $index = $indexes['products'];
        self::assertSame(Document::class, $index['document']);
        self::assertSame([], $index['entities']);
        self::assertSame([self::UID => null], $index['uids']);
        self::assertSame(['name'], $index['searchableAttributes']);
        self::assertSame(['size', 'price', 'taxons', 'collection', 'brand'], $index['filterableAttributes']);
        self::assertSame(['price'], $index['sortableAttributes']);
        self::assertSame(['size', 'price', 'collection', 'brand'], $index['facetableAttributes']);
    }

    /**
     * @test
     */
    public function it_collects_stats_for_resolved_uids(): void
    {
        $client = $this->prophesize(Client::class);
        $client->stats()->willReturn([
            'databaseSize' => 123,
            'indexes' => [
                self::UID => [
                    'numberOfDocuments' => 10,
                    'isIndexing' => false,
                    'fieldDistribution' => ['name' => 10],
                ],
            ],
        ]);

        $collector = $this->createCollector($client->reveal());
        $collector->collect(new Request(), new Response());
        $collector->lateCollect();

        self::assertNull($collector->getStatsError());

        $stats = $collector->getIndexes()['products']['uids'][self::UID];
        self::assertNotNull($stats);
        self::assertSame(10, $stats['numberOfDocuments']);
        self::assertFalse($stats['isIndexing']);
        self::assertInstanceOf(Data::class, $stats['fieldDistribution']);
    }

    /**
     * @test
     */
    public function it_stores_error_when_stats_request_fails(): void
    {
        $client = $this->prophesize(Client::class);
        $client->stats()->willThrow(new \RuntimeException('Connection refused'));

        $collector = $this->createCollector($client->reveal());
        $collector->collect(new Request(), new Response());
        $collector->lateCollect();

        self::assertSame('Connection refused', $collector->getStatsError());
        self::assertSame([self::UID => null], $collector->getIndexes()['products']['uids']);
    }

    /**
     * @test
     */
    public function it_collects_multi_search_requests(): void
    {
        $httpClient = $this->prophesize(ClientInterface::class);
        $httpClient->sendRequest(Argument::type(RequestInterface::class))->willReturn(new HttpResponse(
            200,
            ['content-type' => 'application/json'],
            (string) json_encode(['results' => [['indexUid' => self::UID, 'hits' => []]]]),
        ));

        $client = new TraceableClient('http://localhost:7700', 'masterKey', $httpClient->reveal());
        $client->multiSearch([(new SearchQuery())->setIndexUid(self::UID)->setQuery('hat')]);

        $collector = new MeilisearchDataCollector(
            $client,
            new IndexRegistry(),
            $this->prophesize(IndexScopeProviderInterface::class)->reveal(),
            $this->prophesize(IndexUidResolverInterface::class)->reveal(),
        );
        $collector->collect(new Request(), new Response());

        self::assertTrue($collector->hasMultiSearchRequests());

        $multiSearchRequests = $collector->getMultiSearchRequests();
        self::assertCount(1, $multiSearchRequests);
        self::assertCount(1, $multiSearchRequests[0]['queries']);
        self::assertCount(1, $multiSearchRequests[0]['results']);
    }

    private function createCollector(Client $client): MeilisearchDataCollector
    {
        $eventDispatcher = $this->prophesize(EventDispatcherInterface::class);
        $eventDispatcher->dispatch(Argument::any())->willReturnArgument();

        $container = new Container();
        $container->set(MetadataFactoryInterface::class, new MetadataFactory($eventDispatcher->reveal()));

        $index = new Index('products', Document::class, [], $container);

        $indexRegistry = new IndexRegistry();
        $indexRegistry->add($index);

        $indexScopeProvider = $this->prophesize(IndexScopeProviderInterface::class);
        $indexScopeProvider->getAll($index)->willReturn([new IndexScope($index, 'FASHION_WEB', 'en_US', 'USD')]);

        $indexUidResolver = $this->prophesize(IndexUidResolverInterface::class);
        $indexUidResolver->resolveFromIndexScope(Argument::type(IndexScope::class))->willReturn(self::UID);

        return new MeilisearchDataCollector(
            $client,
            $indexRegistry,
            $indexScopeProvider->reveal(),
            $indexUidResolver->reveal(),
        );
    }
}
