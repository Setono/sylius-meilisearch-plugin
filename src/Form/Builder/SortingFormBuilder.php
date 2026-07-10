<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Form\Builder;

use Setono\SyliusMeilisearchPlugin\Document\Metadata\Metadata;
use Setono\SyliusMeilisearchPlugin\Engine\SearchRequest;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;

final class SortingFormBuilder implements SortingFormBuilderInterface
{
    public function build(FormBuilderInterface $searchFormBuilder, Metadata $metadata): void
    {
        $choices = [];
        foreach ($metadata->sortableAttributes as $sortable) {
            foreach ($sortable->directions() as $direction) {
                $choices[sprintf('setono_sylius_meilisearch.form.search.sorting.%s.%s', $sortable->name, $direction)] = sprintf('%s:%s', $sortable->name, $direction);
            }
        }
        $searchFormBuilder->add(SearchRequest::QUERY_PARAMETER_SORT, ChoiceType::class, [
            'choices' => $choices,
            'required' => false,
            'placeholder' => 'setono_sylius_meilisearch.form.search.sorting.placeholder',
        ]);
    }
}
