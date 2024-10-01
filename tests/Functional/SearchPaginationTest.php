<?php

declare(strict_types=1);

namespace Functional;

use Setono\SyliusMeilisearchPlugin\Engine\SearchEngine;
use Setono\SyliusMeilisearchPlugin\Engine\SearchRequest;
use Setono\SyliusMeilisearchPlugin\Tests\Functional\FunctionalTestCase;

final class SearchPaginationTest extends FunctionalTestCase
{
    public function testItPaginatesSearchResults(): void
    {
        /** @var SearchEngine $searchEngine */
        $searchEngine = self::getContainer()->get(SearchEngine::class);
        $firstPageResult = $searchEngine->execute(new SearchRequest('jeans'));

        self::assertSame(8, $firstPageResult->getHitsCount());
        self::assertSame(3, $firstPageResult->getHitsPerPage());
        self::assertSame(1, $firstPageResult->getPage());
        self::assertSame(3, $firstPageResult->getTotalPages());

        $secondPageResult = $searchEngine->execute(new SearchRequest('jeans', page: 2));
        self::assertSame(2, $secondPageResult->getPage());

        self::assertNotSame($firstPageResult->getHits(), $secondPageResult->getHits());

        $thirdPageResult = $searchEngine->execute(new SearchRequest('jeans', page: 3));
        self::assertSame(3, $thirdPageResult->getPage());
        self::assertCount(2, $thirdPageResult->getHits());
    }
}
