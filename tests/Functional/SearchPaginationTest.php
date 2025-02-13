<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Functional;

use Setono\SyliusMeilisearchPlugin\Engine\SearchEngine;
use Setono\SyliusMeilisearchPlugin\Engine\SearchRequest;

final class SearchPaginationTest extends FunctionalTestCase
{
    public function testItPaginatesSearchResults(): void
    {
        /** @var SearchEngine $searchEngine */
        $searchEngine = self::getContainer()->get(SearchEngine::class);
        $firstPageResult = $searchEngine->execute(new SearchRequest('jeans'));

        self::assertSame(8, $firstPageResult->totalHits);
        self::assertSame(3, $firstPageResult->pageSize);
        self::assertSame(1, $firstPageResult->page);
        self::assertSame(3, $firstPageResult->totalPages);

        $secondPageResult = $searchEngine->execute(new SearchRequest('jeans', page: 2));
        self::assertSame(2, $secondPageResult->page);

        self::assertNotSame($firstPageResult->hits, $secondPageResult->hits);

        $thirdPageResult = $searchEngine->execute(new SearchRequest('jeans', page: 3));
        self::assertSame(3, $thirdPageResult->page);
        self::assertCount(2, $thirdPageResult->hits);
    }
}
