<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Form\Builder;

use Meilisearch\Search\SearchResult;
use Symfony\Component\Form\FormInterface;

interface SearchFormBuilderInterface
{
    public function build(SearchResult $searchResult): FormInterface;
}
