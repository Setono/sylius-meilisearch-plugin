<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Form\Builder;

use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\MetadataFactoryInterface;
use Setono\SyliusMeilisearchPlugin\Engine\SearchRequest;
use Setono\SyliusMeilisearchPlugin\Engine\SearchResult;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
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

        // Pagination is rendered as accessible anchor links (see search/_pagination.html.twig),
        // not as a form field. The `p` query parameter is read straight from the request.

        return $searchFormBuilder->getForm();
    }
}
