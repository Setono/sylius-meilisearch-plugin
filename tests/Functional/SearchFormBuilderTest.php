<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Functional;

use Setono\SyliusMeilisearchPlugin\Engine\SearchEngine;
use Setono\SyliusMeilisearchPlugin\Engine\SearchRequest;
use Setono\SyliusMeilisearchPlugin\Form\Builder\SearchFormBuilderInterface;
use Symfony\Component\Form\ChoiceList\ChoiceListInterface;

final class SearchFormBuilderTest extends FunctionalTestCase
{
    public function testItCreatesFormForSearchResultsWithProperlySortedFacetValues(): void
    {
        /** @var SearchEngine $searchEngine */
        $searchEngine = self::getContainer()->get(SearchEngine::class);
        $result = $searchEngine->execute(new SearchRequest('jeans'));

        /** @var SearchFormBuilderInterface $searchFormBuilder */
        $searchFormBuilder = self::getContainer()->get(SearchFormBuilderInterface::class);

        $form = $searchFormBuilder->build($result);
        /** @var ChoiceListInterface $choiceList */
        $choiceList = $form->get('facets')->get('size')->getConfig()->getAttributes()['choice_list'];

        self::assertSame(
            ['S' => 'S', 'M' => 'M', 'L' => 'L', 'XL' => 'XL', 'XXL' => 'XXL'],
            $choiceList->getChoices(),
        );
    }
}
