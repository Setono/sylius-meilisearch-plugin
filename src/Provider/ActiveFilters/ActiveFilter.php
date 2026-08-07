<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Provider\ActiveFilters;

final class ActiveFilter
{
    public function __construct(
        /** The name of the facet this filter belongs to, e.g. 'brand' */
        public readonly string $facet,

        /** The label to display, e.g. 'Celsius Small' or 'Price: from 50' */
        public readonly string $label,

        /** The url that will remove this filter from the search */
        public readonly string $removeUrl,

        /** The raw filter value for choice filters, null for boolean and range filters */
        public readonly ?string $value = null,
    ) {
    }
}
