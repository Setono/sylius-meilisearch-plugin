<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Meilisearch\Filter;

use Psr\EventDispatcher\EventDispatcherInterface;
use Setono\CompositeCompilerPass\CompositeService;
use Setono\SyliusMeilisearchPlugin\Event\Search\SearchFiltersBuilt;

/** @extends CompositeService<FilterBuilderInterface> */
final class CompositeFilterBuilder extends CompositeService implements FilterBuilderInterface
{
    public function __construct(private readonly EventDispatcherInterface $eventDispatcher)
    {
    }

    public function build(array $facets, array $facetsValues): array
    {
        $filters = [];

        foreach ($this->services as $filterBuilder) {
            $filters[] = $filterBuilder->build($facets, $facetsValues);
        }

        $searchFiltersBuiltEvent = new SearchFiltersBuilt(array_merge(...$filters));
        $this->eventDispatcher->dispatch($searchFiltersBuiltEvent);

        return $searchFiltersBuiltEvent->filters;
    }
}
