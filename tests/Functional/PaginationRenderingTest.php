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
        $container = self::getContainer();

        /** @var RequestStack $requestStack */
        $requestStack = $container->get('request_stack');
        $request = Request::create('/en_US/search?q=jeans&p=2');
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
        $html = $twig->render('@SetonoSyliusMeilisearchPlugin/search/_pagination.html.twig', ['searchResult' => $searchResult]);

        self::assertStringContainsString('<nav class="ssm-pagination"', $html);
        // Keyboard-accessible anchor links, no radio inputs
        self::assertStringContainsString('<a ', $html);
        self::assertStringNotContainsString('<input', $html);
        // On page 2 of 3 there is a prev and a next link, and the current page is marked
        self::assertStringContainsString('rel="prev"', $html);
        self::assertStringContainsString('rel="next"', $html);
        self::assertStringContainsString('aria-current="page"', $html);
    }
}
