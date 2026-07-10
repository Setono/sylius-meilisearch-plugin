<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\Engine;

use Meilisearch\Client;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Container\ContainerInterface;
use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\Facet;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\Metadata;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\MetadataFactoryInterface;
use Setono\SyliusMeilisearchPlugin\Document\Product;
use Setono\SyliusMeilisearchPlugin\Engine\SearchEngine;
use Setono\SyliusMeilisearchPlugin\Engine\SearchRequest;
use Setono\SyliusMeilisearchPlugin\Meilisearch\Query\MultiSearchBuilderInterface;

/**
 * @covers \Setono\SyliusMeilisearchPlugin\Engine\SearchEngine
 */
final class SearchEngineTest extends TestCase
{
    use ProphecyTrait;

    /**
     * The main query returns facet distributions and stats constrained by all filters, while each facet query
     * returns them without the facet's own filter applied (disjunctive faceting). Both must be merged so that
     * the per-facet queries take precedence.
     *
     * @test
     */
    public function it_merges_facet_distributions_and_facet_stats_disjunctively(): void
    {
        $metadata = new Metadata(Product::class);
        $metadata->facetableAttributes = [
            'brand' => new Facet('brand', 'array'),
            'price' => new Facet('price', 'float'),
        ];

        $metadataFactory = $this->prophesize(MetadataFactoryInterface::class);
        $metadataFactory->getMetadataFor(Product::class)->willReturn($metadata);

        $locator = $this->prophesize(ContainerInterface::class);
        $locator->get(MetadataFactoryInterface::class)->willReturn($metadataFactory->reveal());

        $index = new Index('search', Product::class, [], $locator->reveal());

        $multiSearchBuilder = $this->prophesize(MultiSearchBuilderInterface::class);
        $multiSearchBuilder->build($index, Argument::type(SearchRequest::class))->willReturn([]);

        $client = $this->prophesize(Client::class);
        $client->multiSearch([])->willReturn(['results' => [
            [
                'hits' => [],
                'query' => 'jeans',
                'processingTimeMs' => 1,
                'hitsPerPage' => 9,
                'page' => 1,
                'totalPages' => 1,
                'totalHits' => 3,
                'facetDistribution' => [
                    'brand' => ['Celsius Small' => 3],
                    'price' => ['12.99' => 1, '29.99' => 2],
                ],
                'facetStats' => [
                    'price' => ['min' => 12.99, 'max' => 29.99],
                ],
            ],
            [
                'hits' => [],
                'query' => 'jeans',
                'processingTimeMs' => 1,
                'hitsPerPage' => 1,
                'page' => 1,
                'totalPages' => 8,
                'totalHits' => 8,
                'facetDistribution' => [
                    'brand' => ['Celsius Small' => 3, 'Modern Wear' => 5],
                ],
                'facetStats' => [],
            ],
            [
                'hits' => [],
                'query' => 'jeans',
                'processingTimeMs' => 1,
                'hitsPerPage' => 1,
                'page' => 1,
                'totalPages' => 8,
                'totalHits' => 8,
                'facetDistribution' => [
                    'price' => ['5.49' => 1, '12.99' => 1, '99.99' => 1],
                ],
                'facetStats' => [
                    'price' => ['min' => 5.49, 'max' => 99.99],
                ],
            ],
        ]]);

        $searchEngine = new SearchEngine($index, $client->reveal(), $multiSearchBuilder->reveal());

        $searchResult = $searchEngine->execute(new SearchRequest('jeans'));

        $facetDistribution = $searchResult->facetDistribution;

        self::assertSame(['Celsius Small', 'Modern Wear'], $facetDistribution->get('brand')->getValues());

        $priceStats = $facetDistribution->get('price')->stats;
        self::assertNotNull($priceStats);
        self::assertSame(5.49, $priceStats->min);
        self::assertSame(99.99, $priceStats->max);
    }
}
