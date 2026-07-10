<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Provider\ActiveFilters;

use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\Facet;
use Setono\SyliusMeilisearchPlugin\Engine\FacetStats;
use Setono\SyliusMeilisearchPlugin\Engine\SearchRequest;
use Setono\SyliusMeilisearchPlugin\Engine\SearchResult;
use Symfony\Component\HttpFoundation\Request;
use function Symfony\Component\String\u;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ActiveFiltersProvider implements ActiveFiltersProviderInterface
{
    public function __construct(
        private readonly Index $index,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function provide(Request $request, ?SearchResult $searchResult = null): ActiveFilterCollection
    {
        $filters = $request->query->all(SearchRequest::QUERY_PARAMETER_FILTER);
        if ([] === $filters) {
            return new ActiveFilterCollection();
        }

        $facets = $this->index->metadata()->facetableAttributes;

        $activeFilters = [];

        /** @var mixed $values */
        foreach ($filters as $name => $values) {
            $facet = $facets[$name] ?? null;

            // if the facet is not defined in the metadata, the search engine ignores the filter, so no chip is shown
            if (null === $facet) {
                continue;
            }

            $activeFilters[] = match ($facet->type) {
                'array' => $this->provideChoiceFilters($request, $filters, $facet, $values),
                'bool' => $this->provideBooleanFilters($request, $filters, $facet, $values),
                'float', 'int' => $this->provideRangeFilters($request, $filters, $facet, $values, $searchResult),
                default => [],
            };
        }

        $activeFilters = array_merge(...$activeFilters);

        if ([] === $activeFilters) {
            return new ActiveFilterCollection();
        }

        return new ActiveFilterCollection($activeFilters, $this->url($request, []));
    }

    /**
     * @param array<array-key, mixed> $filters
     *
     * @return list<ActiveFilter>
     */
    private function provideChoiceFilters(Request $request, array $filters, Facet $facet, mixed $values): array
    {
        if (is_string($values)) {
            $values = [$values];
        }

        if (!is_array($values)) {
            return [];
        }

        // mirrors the guards in \Setono\SyliusMeilisearchPlugin\Meilisearch\Filter\ArrayFilterBuilder
        $selectedValues = [];

        /** @var mixed $value */
        foreach ($values as $value) {
            if (!is_string($value) || '' === $value) {
                continue;
            }

            $selectedValues[] = $value;
        }

        $activeFilters = [];

        foreach ($selectedValues as $value) {
            $remainingValues = array_values(array_diff($selectedValues, [$value]));

            $activeFilters[] = new ActiveFilter(
                facet: $facet->name,
                label: $value,
                removeUrl: $this->url($request, $this->withFilter($filters, $facet->name, [] === $remainingValues ? null : $remainingValues)),
                value: $value,
            );
        }

        return $activeFilters;
    }

    /**
     * @param array<array-key, mixed> $filters
     *
     * @return list<ActiveFilter>
     */
    private function provideBooleanFilters(Request $request, array $filters, Facet $facet, mixed $values): array
    {
        // mirrors \Setono\SyliusMeilisearchPlugin\Meilisearch\Filter\BooleanFilterBuilder: only the truthy
        // state is reachable through the search form, so only that state produces a filter chip
        if ('1' !== $values && 'true' !== $values && true !== $values && 1 !== $values) {
            return [];
        }

        return [new ActiveFilter(
            facet: $facet->name,
            label: $this->translateFacet($facet, 'setono_sylius_meilisearch.form.search.active_filters.facet.%s'),
            removeUrl: $this->url($request, $this->withFilter($filters, $facet->name, null)),
        )];
    }

    /**
     * @param array<array-key, mixed> $filters
     *
     * @return list<ActiveFilter>
     */
    private function provideRangeFilters(
        Request $request,
        array $filters,
        Facet $facet,
        mixed $values,
        ?SearchResult $searchResult,
    ): array {
        if (!is_array($values)) {
            return [];
        }

        // mirrors the guards in \Setono\SyliusMeilisearchPlugin\Meilisearch\Filter\FloatFilterBuilder
        $min = isset($values['min']) && '' !== $values['min'] && is_numeric($values['min']) ? (string) $values['min'] : null;
        $max = isset($values['max']) && '' !== $values['max'] && is_numeric($values['max']) ? (string) $values['max'] : null;

        if (null === $min && null === $max) {
            return [];
        }

        // The range inputs are prefilled with the facet's bounds and submitted with every form interaction,
        // so a bound only counts as an active filter when it actually narrows the facet's full range.
        // Without stats to compare against we treat the bound as active rather than strand the user
        $stats = $this->stats($facet, $searchResult);

        if (null !== $min && null !== $stats && (float) $min <= $stats->min) {
            $min = null;
        }

        if (null !== $max && null !== $stats && (float) $max >= $stats->max) {
            $max = null;
        }

        if (null === $min && null === $max) {
            return [];
        }

        $facetLabel = $this->translateFacet($facet, 'setono_sylius_meilisearch.form.search.facet.%s');

        $label = match (true) {
            null === $max => $this->translator->trans('setono_sylius_meilisearch.form.search.active_filters.range_min', ['%facet%' => $facetLabel, '%min%' => $min]),
            null === $min => $this->translator->trans('setono_sylius_meilisearch.form.search.active_filters.range_max', ['%facet%' => $facetLabel, '%max%' => $max]),
            default => $this->translator->trans('setono_sylius_meilisearch.form.search.active_filters.range', ['%facet%' => $facetLabel, '%min%' => $min, '%max%' => $max]),
        };

        return [new ActiveFilter(
            facet: $facet->name,
            label: $label,
            removeUrl: $this->url($request, $this->withFilter($filters, $facet->name, null)),
        )];
    }

    private function stats(Facet $facet, ?SearchResult $searchResult): ?FacetStats
    {
        if (null === $searchResult || !$searchResult->facetDistribution->has($facet->name)) {
            return null;
        }

        return $searchResult->facetDistribution->get($facet->name)->stats;
    }

    /**
     * Returns the given filters with the given facet's value replaced (or removed when $value is null)
     *
     * @param array<array-key, mixed> $filters
     *
     * @return array<array-key, mixed>
     */
    private function withFilter(array $filters, string $facet, mixed $value): array
    {
        if (null === $value) {
            unset($filters[$facet]);
        } else {
            $filters[$facet] = $value;
        }

        return $filters;
    }

    /**
     * Builds a url for the current request with the given filters and the page parameter removed
     *
     * @param array<array-key, mixed> $filters
     */
    private function url(Request $request, array $filters): string
    {
        $query = $request->query->all();
        unset($query[SearchRequest::QUERY_PARAMETER_PAGE], $query[SearchRequest::QUERY_PARAMETER_FILTER]);

        if ([] !== $filters) {
            $query[SearchRequest::QUERY_PARAMETER_FILTER] = $filters;
        }

        $queryString = http_build_query($query);

        return $request->getBaseUrl() . $request->getPathInfo() . ('' === $queryString ? '' : '?' . $queryString);
    }

    /**
     * Translates the facet using the given translation key format, falling back
     * to a humanized version of the facet name if the key is not translated
     */
    private function translateFacet(Facet $facet, string $translationKeyFormat): string
    {
        $translationKey = sprintf($translationKeyFormat, u($facet->name)->snake());

        if ($this->translator instanceof TranslatorBagInterface && !$this->translator->getCatalogue()->has($translationKey)) {
            return u($facet->name)->snake()->replace('_', ' ')->title()->toString();
        }

        return $this->translator->trans($translationKey);
    }
}
