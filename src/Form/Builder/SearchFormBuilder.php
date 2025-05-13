<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Form\Builder;

use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\MetadataFactoryInterface;
use Setono\SyliusMeilisearchPlugin\Engine\SearchRequest;
use Setono\SyliusMeilisearchPlugin\Engine\SearchResult;
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

        $facets = $metadata->facetableAttributes;

        foreach ($searchResult->facetDistribution as $name => $values) {
            if (!isset($facets[$name])) {
                continue;
            }

            if ($this->filterFormBuilder->supports($facets[$name], $values)) {
                $this->filterFormBuilder->build($facetsFormBuilder, $facets[$name], $values);
            }
        }

        $searchFormBuilder->add($facetsFormBuilder);

        $this->sortingFormBuilder->build($searchFormBuilder, $metadata);

        $this->buildPagination($searchResult, $searchFormBuilder);

        return $searchFormBuilder->getForm();
    }

    private function buildPagination(SearchResult $searchResult, FormBuilderInterface $builder): void
    {
        $choices = [];

        // current is a special choice that we need to keep the page query parameter in the url on form submission
        $choices['__current'] = $searchResult->page;

        if ($searchResult->page > 1) {
            $choices['setono_sylius_meilisearch.form.search.pagination.previous'] = $searchResult->page - 1;
        }

        if ($searchResult->page < $searchResult->totalPages) {
            $choices['setono_sylius_meilisearch.form.search.pagination.next'] = $searchResult->page + 1;
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
