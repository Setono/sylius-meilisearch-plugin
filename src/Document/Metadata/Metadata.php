<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Document\Metadata;

use Setono\SyliusMeilisearchPlugin\Document\Attribute\Facet as FacetAttribute;
use Setono\SyliusMeilisearchPlugin\Document\Attribute\Filterable as FilterableAttribute;
use Setono\SyliusMeilisearchPlugin\Document\Attribute\Searchable as SearchableAttribute;
use Setono\SyliusMeilisearchPlugin\Document\Attribute\Sortable as SortableAttribute;
use Setono\SyliusMeilisearchPlugin\Document\Document;

final class Metadata implements MetadataInterface
{
    /** @var class-string<Document> */
    private readonly string $document;

    /** @var array<string, Filterable> */
    private array $filterableAttributes = [];

    /** @var array<string, Facet> */
    private array $facetableAttributes = [];

    /** @var array<string, Searchable> */
    private array $searchableAttributes = [];

    /** @var array<string, Sortable> */
    private array $sortableAttributes = [];

    /**
     * @param class-string<Document>|Document $document
     */
    public function __construct(string|Document $document)
    {
        if ($document instanceof Document) {
            $document = $document::class;
        }

        $this->document = $document;

        $this->load();
    }

    private function load(): void
    {
        $documentReflection = new \ReflectionClass($this->document);
        foreach ($documentReflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $reflectionProperty) {
            $this->loadAttributes($reflectionProperty);
        }

        foreach ($documentReflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $reflectionMethod) {
            if (!self::isGetter($reflectionMethod)) {
                continue;
            }

            $this->loadAttributes($reflectionMethod);
        }
    }

    private function loadAttributes(\ReflectionProperty|\ReflectionMethod $attributesAware): void
    {
        $name = self::resolveName($attributesAware);
        if (null === $name) {
            return;
        }

        foreach ($attributesAware->getAttributes() as $reflectionAttribute) {
            $attribute = $reflectionAttribute->newInstance();

            if ($attribute instanceof FilterableAttribute) {
                $this->filterableAttributes[$name] = new Filterable($name);
            }

            if ($attribute instanceof FacetAttribute) {
                $this->facetableAttributes[$name] = new Facet($name);
            }

            if ($attribute instanceof SearchableAttribute) {
                $this->searchableAttributes[$name] = new Searchable($name, $attribute->priority);
            }

            if ($attribute instanceof SortableAttribute) {
                $this->sortableAttributes[$name] = new Sortable($name, $attribute->direction);
            }
        }
    }

    /**
     * @return class-string<Document>
     */
    public function getDocument(): string
    {
        return $this->document;
    }

    public function getFilterableAttributes(): array
    {
        return $this->filterableAttributes;
    }

    public function getFilterableAttributeNames(): array
    {
        return array_keys($this->filterableAttributes);
    }

    public function getFacetableAttributes(): array
    {
        return $this->facetableAttributes;
    }

    public function getFacetableAttributeNames(): array
    {
        return array_keys($this->facetableAttributes);
    }

    public function getSearchableAttributes(): array
    {
        return $this->searchableAttributes;
    }

    public function getSearchableAttributeNames(): array
    {
        $searchableAttributes = $this->searchableAttributes;
        usort($searchableAttributes, static fn (Searchable $a, Searchable $b) => $b->priority <=> $a->priority);

        return array_map(static fn (Searchable $searchableAttribute) => $searchableAttribute->name, $searchableAttributes);
    }

    public function getSortableAttributes(): array
    {
        return $this->sortableAttributes;
    }

    public function getSortableAttributeNames(): array
    {
        return array_keys($this->sortableAttributes);
    }

    private static function isGetter(\ReflectionMethod $reflection): bool
    {
        if ($reflection->getNumberOfParameters() > 0) {
            return false;
        }

        $name = $reflection->getName();

        return str_starts_with($name, 'get') || str_starts_with($name, 'is') || str_starts_with($name, 'has');
    }

    private static function resolveName(\ReflectionProperty|\ReflectionMethod $reflection): ?string
    {
        if ($reflection instanceof \ReflectionProperty) {
            return $reflection->getName();
        }

        if ($reflection->getNumberOfParameters() > 0) {
            return null;
        }

        $name = $reflection->getName();

        foreach (['get', 'is', 'has'] as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return lcfirst(substr($name, strlen($prefix)));
            }
        }

        return null;
    }
}
