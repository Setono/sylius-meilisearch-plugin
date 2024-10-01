<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\Engine;

use PHPUnit\Framework\TestCase;
use Setono\SyliusMeilisearchPlugin\Engine\SearchRequest;
use Symfony\Component\HttpFoundation\Request;

final class SearchRequestTest extends TestCase
{
    /**
     * @test
     */
    public function it_creates_search_request(): void
    {
        $searchRequest = new SearchRequest(
            'jeans',
            ['brand' => ['Celsius small', 'You are breathtaking'], 'price' => ['min' => '30', 'max' => '45']],
            2,
            'price:asc',
        );

        self::assertSame('jeans', $searchRequest->query);
        self::assertSame(['brand' => ['Celsius small', 'You are breathtaking'], 'price' => ['min' => '30', 'max' => '45']], $searchRequest->filters);
        self::assertSame(2, $searchRequest->page);
        self::assertSame('price:asc', $searchRequest->sort);
    }

    /**
     * @test
     */
    public function it_creates_from_request(): void
    {
        $request = Request::create('/search', 'GET', ['q' => 'jeans', 'facets' => ['brand' => ['Celsius small', 'You are breathtaking'], 'price' => ['min' => '30', 'max' => '45']], 'p' => 2, 'sort' => 'price:asc']);
        $searchRequest = SearchRequest::fromRequest($request);

        self::assertSame('jeans', $searchRequest->query);
        self::assertSame(['brand' => ['Celsius small', 'You are breathtaking'], 'price' => ['min' => '30', 'max' => '45']], $searchRequest->filters);
        self::assertSame(2, $searchRequest->page);
        self::assertSame('price:asc', $searchRequest->sort);
    }
}
