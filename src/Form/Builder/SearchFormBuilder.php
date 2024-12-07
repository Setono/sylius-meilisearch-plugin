<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Form\Builder;

use Meilisearch\Search\SearchResult;
use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\MetadataFactoryInterface;
use Setono\SyliusMeilisearchPlugin\Engine\SearchRequest;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

final class SearchFormBuilder implements SearchFormBuilderInterface
{
    public function __construct(
        private readonly FormFactoryInterface $formFactory,
        private readonly FilterFormBuilderInterface $filterFormBuilder,
        private readonly SortingFormBuilderInterface $sortingFormBuilder,
        private readonly MetadataFactoryInterface $metadataFactory,
        private readonly Index $index,
    ) {
    }

    public function build(SearchResult $searchResult): FormInterface
    {
        $metadata = $this->metadataFactory->getMetadataFor($this->index->document);

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
        $facetsFormBuilder = $this->formFactory->createNamedBuilder(SearchRequest::QUERY_PARAMETER_FILTER);

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

        $facets = $metadata->getFacetableAttributes();

        /**
         * Here is an example of the facet distribution array
         *
         * "brand" => [
         *   "Celsius Small" => 3
         *   "Date & Banana" => 2
         *   "Modern Wear" => 6
         *   "You are breathtaking" => 10
         * ]
         * "hierarchicalTaxons" => []
         * "hierarchicalTaxons.level0" => [
         *   "Category" => 21
         * ]
         * "hierarchicalTaxons.level1" => [
         *   "Category > Caps" => 4
         *   "Category > Dresses" => 3
         *   "Category > Jeans" => 8
         *   "Category > T-shirts" => 6
         * ]
         * "hierarchicalTaxons.level2" => [
         *   "Category > Caps > Simple" => 2
         *   "Category > Caps > With pompons" => 2
         *   "Category > Jeans > Men" => 4
         *   "Category > Jeans > Women" => 4
         *   "Category > T-shirts > Men" => 3
         *   "Category > T-shirts > Women" => 3
         * ]
         * "onSale" => [
         *   "false" => 16
         *   "true" => 5
         * ]
         * "price" => [
         *   "15.12" => 1
         *   "16.08" => 1
         *   "18.92" => 1
         *   "19.83" => 1
         *   "20.78" => 1
         * ]
         * "size" => [
         *   "L" => 17
         *   "M" => 17
         *   "S" => 17
         *   "XL" => 17
         *   "XXL" => 17
         * ]
         *
         * @var string $name
         * @var array<string, int> $values
         */
        foreach ($searchResult->getFacetDistribution() as $name => $values) {
            if (!isset($facets[$name])) {
                continue;
            }

            if ($this->filterFormBuilder->supports($facets[$name], $values, $facetStats[$name] ?? null)) {
                $this->filterFormBuilder->build($facetsFormBuilder, $facets[$name], $values, $facetStats[$name] ?? null);
            }
        }

        $searchFormBuilder->add($facetsFormBuilder);

        $this->sortingFormBuilder->build($searchFormBuilder, $metadata);

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
