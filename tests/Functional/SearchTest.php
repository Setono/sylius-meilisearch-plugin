<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Functional;

use Setono\SyliusMeilisearchPlugin\Engine\SearchEngine;

/** @group functional */
final class SearchTest extends FunctionalTestCase
{
    public function testItProvidesSearchResults(): void
    {
        /** @var SearchEngine $searchEngine */
        $searchEngine = self::getContainer()->get(SearchEngine::class);
        $result = $searchEngine->execute('jeans');

        self::assertSame(8, $result->getHitsCount());
    }

    public function testItAlwaysDisplaysFullFacetDistribution(): void
    {
        /** @var SearchEngine $searchEngine */
        $searchEngine = self::getContainer()->get(SearchEngine::class);
        $result = $searchEngine->execute(
            'jeans',
            ['facets' => ['brand' => ['Celsius small']]],
        );

        $this->assertSame(1, $result->getHitsCount());
        $this->assertCount(4, (array) $result->getFacetDistribution()['brand']);
    }
}
