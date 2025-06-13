<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Form\Type;

use Setono\SyliusMeilisearchPlugin\Engine\SearchRequest;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

final class SearchResultType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add(SearchRequest::QUERY_PARAMETER_SEARCH, HiddenType::class, [
                'property_path' => 'query',
            ])
//            ->add(SearchRequest::QUERY_PARAMETER_FILTER, SearchFilterType::class)
            ->add(SearchRequest::QUERY_PARAMETER_SORT, SearchResultSortType::class, [
                'property_path' => 'sort',
            ])
            ->setMethod('GET')
        ;
    }
}
