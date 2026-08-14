<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Functional;

final class TaxonPageTest extends FunctionalTestCase
{
    public function testItServesTheTaxonPageWithTheMeilisearchSearchPage(): void
    {
        // The slug contains a slash on purpose: it exercises the route's `.+(?<!/)` slug requirement
        self::$client->request('GET', '/en_US/taxons/category/jeans');

        self::assertResponseIsSuccessful();

        $content = (string) self::$client->getResponse()->getContent();

        // The Meilisearch search page markup, not Sylius' stock grid
        self::assertStringContainsString('id="search-form"', $content);
        self::assertStringContainsString('ssm-hits-count', $content);

        // The taxon was resolved from the slug: the Sylius taxon header is rendered
        self::assertMatchesRegularExpression('#<h1 class="ui monster section dividing header">\s*Jeans#', $content);

        // The takeover keeps the sylius_shop_product_index route name, so Sylius' data-route styling hooks still apply
        self::assertStringContainsString('data-route="sylius_shop_product_index"', $content);
    }
}
