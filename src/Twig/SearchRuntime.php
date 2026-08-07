<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Twig;

use Setono\SyliusMeilisearchPlugin\Engine\SearchResult;
use Setono\SyliusMeilisearchPlugin\Provider\ActiveFilters\ActiveFilterCollection;
use Setono\SyliusMeilisearchPlugin\Provider\ActiveFilters\ActiveFiltersProviderInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\RuntimeExtensionInterface;

final class SearchRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly ActiveFiltersProviderInterface $activeFiltersProvider,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function activeFilters(?SearchResult $searchResult = null): ActiveFilterCollection
    {
        $request = $this->requestStack->getMainRequest();
        if (null === $request) {
            return new ActiveFilterCollection();
        }

        return $this->activeFiltersProvider->provide($request, $searchResult);
    }
}
