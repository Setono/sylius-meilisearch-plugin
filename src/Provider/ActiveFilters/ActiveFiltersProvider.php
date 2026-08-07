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
        // The chips are derived from the query string because that is what a remove link can change.
        // Which of them are actually active is decided by the request the search was executed with:
        // listeners of the SearchRequestCreated event may change the filters before the search runs
        $filters = SearchRequest::fromRequest($request)->filters;
        if ([] === $filters) {
            return new ActiveFilterCollection();
        }

        $appliedFilters = $searchResult?->request?->filters;

        $facets = $this->index->metadata()->facetableAttributes;

        $activeFilters = [];

        /** @var mixed $values */
        foreach ($filters as $name => $values) {
            $facet = $facets[$name] ?? null;

            // if the facet is not defined in the metadata, the search engine ignores the filter, so no chip is shown
            if (null === $facet) {
                continue;
            }

            /** @var mixed $applied */
            $applied = null === $appliedFilters ? $values : ($appliedFilters[$name] ?? null);

            // the filter is in the url, but the search did not use it, so it constrains nothing
            if (null === $applied) {
                continue;
            }

            $activeFilters[] = match ($facet->type) {
                'array' => $this->provideChoiceFilters($request, $filters, $facet, $values, $applied),
                'bool' => $this->provideBooleanFilters($request, $filters, $facet, $applied),
                'float', 'int' => $this->provideRangeFilters($request, $filters, $facet, $applied, $searchResult),
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
    private function provideChoiceFilters(Request $request, array $filters, Facet $facet, mixed $values, mixed $appliedValues): array
    {
        $selectedValues = self::choiceValues($values);
        $appliedValues = self::choiceValues($appliedValues);

        // a value the search did not use constrains nothing, and a value that is not in the url
        // cannot be removed through one either, so only the values in both get a chip
        $selectedValues = array_values(array_intersect($selectedValues, $appliedValues));

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
     * Normalizes a choice facet's filter value to the list of values the search engine filters on.
     * Mirrors the guards in \Setono\SyliusMeilisearchPlugin\Meilisearch\Filter\ArrayFilterBuilder
     *
     * @return list<string>
     */
    private static function choiceValues(mixed $values): array
    {
        if (is_string($values)) {
            $values = [$values];
        }

        if (!is_array($values)) {
            return [];
        }

        $choiceValues = [];

        /** @var mixed $value */
        foreach ($values as $value) {
            if (!is_string($value) || '' === $value) {
                continue;
            }

            $choiceValues[] = $value;
        }

        return $choiceValues;
    }

    /**
     * @param array<array-key, mixed> $filters
     *
     * @return list<ActiveFilter>
     */
    private function provideBooleanFilters(Request $request, array $filters, Facet $facet, mixed $appliedValue): array
    {
        // mirrors \Setono\SyliusMeilisearchPlugin\Meilisearch\Filter\BooleanFilterBuilder: only the truthy
        // state is reachable through the search form, so only that state produces a filter chip
        if ('1' !== $appliedValue && 'true' !== $appliedValue && true !== $appliedValue && 1 !== $appliedValue) {
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
        mixed $appliedValues,
        ?SearchResult $searchResult,
    ): array {
        if (!is_array($appliedValues)) {
            return [];
        }

        // mirrors the guards in \Setono\SyliusMeilisearchPlugin\Meilisearch\Filter\FloatFilterBuilder
        $min = isset($appliedValues['min']) && '' !== $appliedValues['min'] && is_numeric($appliedValues['min']) ? (string) $appliedValues['min'] : null;
        $max = isset($appliedValues['max']) && '' !== $appliedValues['max'] && is_numeric($appliedValues['max']) ? (string) $appliedValues['max'] : null;

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
        $translation = $this->translator->trans($translationKey);

        // the translator returns the key itself when it has no translation for it. A custom facet
        // is unlikely to have one, and a raw translation key in a filter chip would look broken
        if ($translation === $translationKey) {
            return u($facet->name)->snake()->replace('_', ' ')->title()->toString();
        }

        return $translation;
    }
}
