<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Form\Builder;

use Meilisearch\Search\SearchResult;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

final class SearchFormBuilder implements SearchFormBuilderInterface
{
    public function __construct(
        private readonly FormFactoryInterface $formFactory,
        private readonly FacetFormBuilderInterface $facetFormBuilder,
    ) {
    }

    public function build(SearchResult $searchResult): FormInterface
    {
        $searchFormBuilder = $this
            ->formFactory
            ->createNamedBuilder('', options: [
                'csrf_protection' => false,
                'allow_extra_fields' => true,
            ])
            ->add('q', HiddenType::class)
            ->setMethod('GET')
        ;

        // todo this nesting makes the URLs uglier. Can we do something else?
        // todo could this be fixed by adding the facet forms to some kind of collection?
        $facetsFormBuilder = $this->formFactory->createNamedBuilder('facets');

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
                $this->facetFormBuilder->build($facetsFormBuilder, $name, $values, $facetStats[$name] ?? null);
            }
        }

        $searchFormBuilder->add($facetsFormBuilder);

        $searchFormBuilder->add('sort', ChoiceType::class, [
            'choices' => [
                'Price: Low to High' => 'price:asc',
                'Price: High to Low' => 'price:desc',
            ],
            'required' => false,
            'placeholder' => 'Sort by',
        ]);

        $this->buildPagination($searchResult, $searchFormBuilder);

        return $searchFormBuilder->getForm();
    }

    private function buildPagination(SearchResult $searchResult, FormBuilderInterface $builder): void
    {
        $page = $searchResult->getPage();
        if (null === $page) {
            return;
        }

        $choices = [];

        // current is a special choice that we need to keep the page query parameter in the url on form submission
        $choices['__current'] = $page;

        if ($searchResult->getPage() > 1) {
            $choices['setono_sylius_meilisearch.form.search.pagination.previous'] = $page - 1;
        }

        if ($searchResult->getPage() < $searchResult->getTotalPages()) {
            $choices['setono_sylius_meilisearch.form.search.pagination.next'] = $page + 1;
        }

        $builder->add('p', ChoiceType::class, [
            'choices' => $choices,
            'choice_attr' => fn (string $page) => ['style' => 'display: none'], // we only want to display the labels
            'required' => false,
            'expanded' => true,
            'placeholder' => false,
            'block_prefix' => 'setono_sylius_meilisearch_page_choice',
        ]);
    }
}
