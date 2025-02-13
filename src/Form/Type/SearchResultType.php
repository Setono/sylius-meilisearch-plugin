<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Form\Type;

use Setono\SyliusMeilisearchPlugin\Engine\SearchRequest;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;

final class SearchResultType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add(SearchRequest::QUERY_PARAMETER_SEARCH, HiddenType::class)
            ->add(SearchRequest::QUERY_PARAMETER_FILTER, SearchFilterType::class)
            ->add(SearchRequest::QUERY_PARAMETER_SORT, SearchSortType::class)
            ->setMethod('GET')
        ;
    }
}
