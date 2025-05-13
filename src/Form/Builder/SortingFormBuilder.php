<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Form\Builder;

use Setono\SyliusMeilisearchPlugin\Document\Attribute\Sortable as SortableAttribute;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\Metadata;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\Sortable;
use Setono\SyliusMeilisearchPlugin\Engine\SearchRequest;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;

final class SortingFormBuilder implements SortingFormBuilderInterface
{
    public function build(FormBuilderInterface $searchFormBuilder, Metadata $metadata): void
    {
        $choices = [];
        foreach ($metadata->sortableAttributes as $sortable) {
            foreach (self::resolveDirections($sortable) as $direction) {
                $choices[sprintf('setono_sylius_meilisearch.form.search.sorting.%s.%s', $sortable->name, $direction)] = sprintf('%s:%s', $sortable->name, $direction);
            }
        }
        $searchFormBuilder->add(SearchRequest::QUERY_PARAMETER_SORT, ChoiceType::class, [
            'choices' => $choices,
            'required' => false,
            'placeholder' => 'setono_sylius_meilisearch.form.search.sorting.placeholder',
        ]);
    }

    /**
     * @return list<string>
     */
    private static function resolveDirections(Sortable $sortable): array
    {
        if (null === $sortable->direction) {
            return [SortableAttribute::ASC, SortableAttribute::DESC];
        }

        return [$sortable->direction];
    }
}
