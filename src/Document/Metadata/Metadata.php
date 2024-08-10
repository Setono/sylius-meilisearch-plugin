<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Document\Metadata;

use Setono\SyliusMeilisearchPlugin\Document\Attribute\Facet as FacetAttribute;
use Setono\SyliusMeilisearchPlugin\Document\Document;

final class Metadata implements MetadataInterface
{
    /** @var class-string<Document> */
    private readonly string $document;

    /** @var list<Facet> */
    private array $facets = [];

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
            foreach ($reflectionProperty->getAttributes() as $reflectionAttribute) {
                $attribute = $reflectionAttribute->newInstance();
                if ($attribute instanceof FacetAttribute) {
                    $this->facets[] = new Facet($reflectionProperty->getName());
                }
            }
        }

        foreach ($documentReflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $reflectionMethod) {
            $property = self::getterProperty($reflectionMethod);
            if (null === $property) {
                continue;
            }

            foreach ($reflectionMethod->getAttributes() as $reflectionAttribute) {
                $attribute = $reflectionAttribute->newInstance();

                if ($attribute instanceof FacetAttribute) {
                    $this->facets[] = new Facet($property);
                }
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

    public function getFacets(): array
    {
        return $this->facets;
    }

    private static function getterProperty(\ReflectionMethod $reflectionMethod): ?string
    {
        if ($reflectionMethod->getNumberOfParameters() > 0) {
            return null;
        }

        $name = $reflectionMethod->getName();

        foreach (['get', 'is', 'has'] as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return lcfirst(substr($name, strlen($prefix)));
            }
        }

        return null;
    }
}
