<?php

declare(strict_types=1);

namespace Functional;

use Setono\SyliusMeilisearchPlugin\Engine\SearchEngine;
use Setono\SyliusMeilisearchPlugin\Form\Builder\SearchFormBuilderInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** @group functional */
final class SearchFormBuilderTest extends WebTestCase
{
    private static KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();

        self::ensureKernelShutdown();
        self::$client = static::createClient(['environment' => 'test', 'debug' => true]);
    }

    public function testItCreatesFormForSearchResultsWithProperlySortedFacetValues(): void
    {
        /** @var SearchEngine $searchEngine */
        $searchEngine = self::getContainer()->get(SearchEngine::class);
        $result = $searchEngine->execute('jeans');

        /** @var SearchFormBuilderInterface $searchFormBuilder */
        $searchFormBuilder = self::getContainer()->get(SearchFormBuilderInterface::class);

        $form = $searchFormBuilder->build($result);

        self::assertSame(
            ['S' => 'S', 'M' => 'M', 'L' => 'L', 'XL' => 'XL', 'XXL' => 'XXL'],
            $form->get('facets')->get('size')->getConfig()->getAttributes()['choice_list']->getChoices(),
        );
    }
}
