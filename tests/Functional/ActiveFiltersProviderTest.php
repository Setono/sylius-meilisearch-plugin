<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Functional;

use Setono\SyliusMeilisearchPlugin\Engine\SearchEngine;
use Setono\SyliusMeilisearchPlugin\Engine\SearchRequest;
use Setono\SyliusMeilisearchPlugin\Provider\ActiveFilters\ActiveFiltersProviderInterface;
use Symfony\Component\HttpFoundation\Request;

final class ActiveFiltersProviderTest extends FunctionalTestCase
{
    public function testItProvidesActiveFiltersForRequest(): void
    {
        /** @var SearchEngine $searchEngine */
        $searchEngine = self::getContainer()->get(SearchEngine::class);
        $searchResult = $searchEngine->execute(new SearchRequest('jeans', ['brand' => ['Celsius Small']]));

        /** @var ActiveFiltersProviderInterface $activeFiltersProvider */
        $activeFiltersProvider = self::getContainer()->get(ActiveFiltersProviderInterface::class);

        $activeFilters = $activeFiltersProvider->provide(
            Request::create('/en_US/search?q=jeans&f[brand][]=Celsius Small'),
            $searchResult,
        );

        self::assertCount(1, $activeFilters);
        self::assertSame('brand', $activeFilters->filters[0]->facet);
        self::assertSame('Celsius Small', $activeFilters->filters[0]->label);
        self::assertSame('/en_US/search?q=jeans', $activeFilters->filters[0]->removeUrl);
        self::assertSame('/en_US/search?q=jeans', $activeFilters->resetUrl);
    }

    public function testItSuppressesRangeFiltersThatDoNotNarrowTheFacetBounds(): void
    {
        /** @var SearchEngine $searchEngine */
        $searchEngine = self::getContainer()->get(SearchEngine::class);
        $searchResult = $searchEngine->execute(new SearchRequest('jeans'));

        // The search form prefills the price inputs with the facet's bounds and submits them
        // with every interaction, so bounds equal to the facet stats must not produce a chip
        $stats = $searchResult->facetDistribution->get('price')->stats;
        self::assertNotNull($stats);

        /** @var ActiveFiltersProviderInterface $activeFiltersProvider */
        $activeFiltersProvider = self::getContainer()->get(ActiveFiltersProviderInterface::class);

        $activeFilters = $activeFiltersProvider->provide(
            Request::create(sprintf('/en_US/search?q=jeans&f[price][min]=%s&f[price][max]=%s', $stats->min, $stats->max)),
            $searchResult,
        );

        self::assertTrue($activeFilters->isEmpty());
    }
}
