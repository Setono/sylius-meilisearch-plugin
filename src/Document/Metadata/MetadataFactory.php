<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Document\Metadata;

use Psr\EventDispatcher\EventDispatcherInterface;
use Setono\SyliusMeilisearchPlugin\Document\Attribute\Facetable as FacetableAttribute;
use Setono\SyliusMeilisearchPlugin\Document\Attribute\Filterable as FilterableAttribute;
use Setono\SyliusMeilisearchPlugin\Document\Attribute\Image as ImageAttribute;
use Setono\SyliusMeilisearchPlugin\Document\Attribute\MapProductAttribute;
use Setono\SyliusMeilisearchPlugin\Document\Attribute\MapProductOption;
use Setono\SyliusMeilisearchPlugin\Document\Attribute\Searchable as SearchableAttribute;
use Setono\SyliusMeilisearchPlugin\Document\Attribute\Sortable as SortableAttribute;
use Setono\SyliusMeilisearchPlugin\Document\Document;
use Setono\SyliusMeilisearchPlugin\Event\MetadataCreated;
use Webmozart\Assert\Assert;

final class MetadataFactory implements MetadataFactoryInterface
{
    public function __construct(private readonly EventDispatcherInterface $eventDispatcher)
    {
    }

    public function getMetadataFor(string|Document $document): Metadata
    {
        $metadata = new Metadata($document);

        $documentReflection = new \ReflectionClass($document);
        foreach ($documentReflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $reflectionProperty) {
            $this->loadAttributes($metadata, $reflectionProperty);
        }

        foreach ($documentReflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $reflectionMethod) {
            if (!self::isGetter($reflectionMethod)) {
                continue;
            }

            $this->loadAttributes($metadata, $reflectionMethod);
        }

        $this->eventDispatcher->dispatch(new MetadataCreated($metadata));

        return $metadata;
    }

    private function loadAttributes(Metadata $metadata, \ReflectionProperty|\ReflectionMethod $attributesAware): void
    {
        $name = self::resolveName($attributesAware);
        if (null === $name) {
            return;
        }

        foreach ($attributesAware->getAttributes() as $reflectionAttribute) {
            $attribute = $reflectionAttribute->newInstance();

            if ($attribute instanceof FilterableAttribute) {
                $metadata->filterableAttributes[$name] = new Filterable($name);
            }

            if ($attribute instanceof FacetableAttribute) {
                $metadata->facetableAttributes[$name] = new Facet(
                    $name,
                    self::getFacetType($attributesAware),
                    $attribute->position,
                    $attribute->sorter ?? null,
                );
            }

            if ($attribute instanceof SearchableAttribute) {
                $metadata->searchableAttributes[$name] = new Searchable($name, $attribute->priority);
            }

            if ($attribute instanceof SortableAttribute) {
                $metadata->sortableAttributes[$name] = new Sortable($name, $attribute->direction);
            }

            if ($attribute instanceof MapProductOption) {
                Assert::isInstanceOf($attributesAware, \ReflectionProperty::class);
                // todo are we sure this needs to be an array?
                Assert::same('array', (string) $attributesAware->getType());
                $metadata->mappedProductOptions[$name] = $attribute->codes;
            }

            if ($attribute instanceof MapProductAttribute) {
                Assert::isInstanceOf($attributesAware, \ReflectionProperty::class);
                $metadata->mappedProductAttributes[] = new MappedProductAttribute($name, (string) $attributesAware->getType(), $attribute->codes);
            }

            if ($attribute instanceof ImageAttribute) {
                $metadata->imageAttributes[$name] = new Image($name, $attribute->filterSet, $attribute->type);
            }
        }
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

    private static function getFacetType(\ReflectionProperty|\ReflectionMethod $attributesAware): string
    {
        if ($attributesAware instanceof \ReflectionProperty) {
            return str_replace('?', '', (string) $attributesAware->getType());
        }

        return str_replace('?', '', (string) $attributesAware->getReturnType());
    }
}
