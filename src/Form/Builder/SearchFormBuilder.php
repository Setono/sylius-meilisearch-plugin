<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Form\Builder;

use Meilisearch\Search\SearchResult;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

final class SearchFormBuilder implements SearchFormBuilderInterface
{
    public function __construct(private readonly FormFactoryInterface $formFactory, private readonly FacetFormBuilderInterface $facetFormBuilder)
    {
    }

    public function build(SearchResult $searchResult): FormInterface
    {
        $builder = $this->formFactory->createNamedBuilder('', options: [
            'csrf_protection' => false,
        ]);

        /**
         * Here is an example of the facet stats array
         *
         * [
         *   "price" => [
         *     "min" => 1.24
         *     "max" => 47.42
         *   ]
         * ]
         *
         * @var array<string, array{min: int|float, max: int|float}> $facetStats
         */
        $facetStats = $searchResult->getFacetStats();

        /**
         * Here is an example of the facet distribution array
         *
         * [
         *   "onSale" => [
         *     "false" => 16
         *     "true" => 1
         *   ]
         *   "size" => [
         *     "L" => 17
         *     "M" => 17
         *     "S" => 17
         *     "XL" => 17
         *     "XXL" => 17
         *   ]
         * ]
         *
         * @var string $name
         * @var array<string, int> $values
         */
        foreach ($searchResult->getFacetDistribution() as $name => $values) {
            if ($this->facetFormBuilder->supports($name, $values, $facetStats[$name] ?? null)) {
                $this->facetFormBuilder->build($builder, $name, $values, $facetStats[$name] ?? null);
            }
        }

        $builder->setMethod('GET');

        return $builder->getForm();
    }
}
