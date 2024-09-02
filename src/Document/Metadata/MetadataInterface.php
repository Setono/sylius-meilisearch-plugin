<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Document\Metadata;

use Setono\SyliusMeilisearchPlugin\Document\Document;

interface MetadataInterface
{
    /**
     * @return class-string<Document>
     */
    public function getDocument(): string;

    /**
     * Filterable attributes indexed by the name
     *
     * @return array<string, Filterable>
     */
    public function getFilterableAttributes(): array;

    /**
     * Returns the names of the filterable attributes
     *
     * @return list<string>
     */
    public function getFilterableAttributeNames(): array;

    /**
     * Facetable attributes indexed by the name
     *
     * @return array<string, Facet>
     */
    public function getFacetableAttributes(): array;

    /**
     * Returns the names of the facetable attributes
     *
     * @return array<string>
     */
    public function getFacetableAttributeNames(): array;

    /**
     * Searchable attributes indexed by the name
     *
     * @return array<string, Searchable>
     */
    public function getSearchableAttributes(): array;

    /**
     * Returns the names of the searchable attributes (sorted by priority)
     *
     * @return list<string>
     */
    public function getSearchableAttributeNames(): array;

    /**
     * Sortable attributes indexed by the name
     *
     * @return array<string, Sortable>
     */
    public function getSortableAttributes(): array;

    /**
     * Returns the names of the sortable attributes
     *
     * @return list<string>
     */
    public function getSortableAttributeNames(): array;
}
