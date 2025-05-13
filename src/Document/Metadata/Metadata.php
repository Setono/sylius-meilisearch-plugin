<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Document\Metadata;

use Setono\SyliusMeilisearchPlugin\Document\Document;

final class Metadata
{
    /** @var class-string<Document> */
    public readonly string $document;

    /**
     * Filterable attributes indexed by the name
     *
     * @var array<string, Filterable>
     */
    public array $filterableAttributes = [];

    /**
     * Facetable attributes indexed by the name
     *
     * @var array<string, Facet>
     */
    public array $facetableAttributes = [];

    /**
     * Searchable attributes indexed by the name
     *
     * @var array<string, Searchable>
     */
    public array $searchableAttributes = [];

    /**
     * Sortable attributes indexed by the name
     *
     * @var array<string, Sortable>
     */
    public array $sortableAttributes = [];

    /**
     * Product options codes mapped by document property
     *
     * @var array<string, list<string>>
     */
    public array $mappedProductOptions = [];

    /** @var list<MappedProductAttribute> */
    public array $mappedProductAttributes = [];

    /** @var array<string, Image> */
    public array $imageAttributes = [];

    /**
     * @param class-string<Document>|Document $document
     */
    public function __construct(string|Document $document)
    {
        if ($document instanceof Document) {
            $document = $document::class;
        }

        $this->document = $document;
    }

    /**
     * Returns the names of the filterable attributes
     *
     * @return list<string>
     */
    public function getFilterableAttributeNames(): array
    {
        return array_keys($this->filterableAttributes);
    }

    /**
     * Returns the names of the facetable attributes
     *
     * @return list<string>
     */
    public function getFacetableAttributeNames(): array
    {
        return array_keys($this->facetableAttributes);
    }

    /**
     * Returns the names of the searchable attributes (sorted by priority)
     *
     * @return list<string>
     */
    public function getSearchableAttributeNames(): array
    {
        $searchableAttributes = $this->searchableAttributes;
        usort($searchableAttributes, static fn (Searchable $a, Searchable $b) => $b->priority <=> $a->priority);

        return array_map(static fn (Searchable $searchableAttribute) => $searchableAttribute->name, $searchableAttributes);
    }

    /**
     * Returns the names of the sortable attributes
     *
     * @return list<string>
     */
    public function getSortableAttributeNames(): array
    {
        return array_keys($this->sortableAttributes);
    }
}
