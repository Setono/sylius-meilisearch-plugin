<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Functional;

use Setono\SyliusMeilisearchPlugin\Config\IndexRegistryInterface;
use Setono\SyliusMeilisearchPlugin\Engine\FacetDistribution;
use Setono\SyliusMeilisearchPlugin\Engine\SearchResult;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;

final class PaginationRenderingTest extends FunctionalTestCase
{
    public function testItRendersAccessibleAnchorPagination(): void
    {
        $html = $this->renderPagination('/en_US/search?q=jeans&p=2');

        self::assertStringContainsString('<nav class="ssm-pagination"', $html);
        // Keyboard-accessible anchor links, no radio inputs
        self::assertStringContainsString('<a ', $html);
        self::assertStringNotContainsString('<input', $html);
        // On page 2 of 3 there is a prev and a next link, and the current page is marked
        self::assertStringContainsString('rel="prev"', $html);
        self::assertStringContainsString('rel="next"', $html);
        self::assertStringContainsString('aria-current="page"', $html);
    }

    public function testItKeepsTheQueryInThePageLinks(): void
    {
        foreach (self::hrefs($this->renderPagination('/en_US/search?q=jeans&p=2')) as $href) {
            self::assertStringStartsWith('/en_US/search?', $href);
            self::assertStringContainsString('q=jeans', $href);
        }
    }

    /**
     * A query key named like a route parameter must not replace that parameter in the page links:
     * the route's own parameters win. Otherwise ?_locale=/evil.example would point the links at
     * another host where the router doesn't refuse the value, and throw where it does.
     */
    public function testQueryKeysNamedLikeRouteParametersDoNotChangeThePageLinks(): void
    {
        $hrefs = self::hrefs($this->renderPagination('/en_US/search?q=jeans&p=2&_locale=/evil.example'));

        self::assertNotEmpty($hrefs);
        foreach ($hrefs as $href) {
            self::assertStringStartsWith('/en_US/search?', $href);
            self::assertStringNotContainsString('evil.example', $href);
            self::assertStringContainsString('q=jeans', $href);
        }
    }

    private function renderPagination(string $uri): string
    {
        $container = self::getContainer();

        /** @var RequestStack $requestStack */
        $requestStack = $container->get('request_stack');
        $request = Request::create($uri);
        $request->attributes->set('_route', 'setono_sylius_meilisearch_shop_search');
        $request->attributes->set('_route_params', ['_locale' => 'en_US']);
        $requestStack->push($request);

        /** @var IndexRegistryInterface $indexRegistry */
        $indexRegistry = $container->get('setono_sylius_meilisearch.config.index_registry');
        $searchResult = new SearchResult(
            $indexRegistry->get('products'),
            [],
            totalHits: 8,
            page: 2,
            pageSize: 3,
            totalPages: 3,
            facetDistribution: new FacetDistribution([], []),
        );

        /** @var Environment $twig */
        $twig = $container->get('twig');

        return $twig->render('@SetonoSyliusMeilisearchPlugin/search/_pagination.html.twig', ['searchResult' => $searchResult]);
    }

    /**
     * @return list<string>
     */
    private static function hrefs(string $html): array
    {
        preg_match_all('/href="([^"]*)"/', $html, $matches);

        return array_map(static fn (string $href): string => html_entity_decode($href, \ENT_QUOTES | \ENT_HTML5), $matches[1]);
    }
}
