<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Event\Search;

use Setono\SyliusMeilisearchPlugin\Engine\SearchRequest;
use Symfony\Component\HttpFoundation\Request;

final class SearchRequestCreated
{
    public function __construct(public readonly Request $request, public SearchRequest $searchRequest)
    {
    }
}
