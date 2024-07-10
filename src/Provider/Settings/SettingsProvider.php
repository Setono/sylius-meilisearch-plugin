<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Provider\Settings;

use Setono\SyliusMeilisearchPlugin\Document\Attribute\Filterable;
use Setono\SyliusMeilisearchPlugin\Document\Attribute\Searchable;
use Setono\SyliusMeilisearchPlugin\Document\Attribute\Sortable;
use Setono\SyliusMeilisearchPlugin\Meilisearch\SynonymResolverInterface;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScope;
use Setono\SyliusMeilisearchPlugin\Settings\Settings;

final class SettingsProvider implements SettingsProviderInterface
{
    public function __construct(private readonly SynonymResolverInterface $synonymResolver)
    {
    }

    public function getSettings(IndexScope $indexScope): Settings
    {
        $settings = new Settings();

        $documentReflection = new \ReflectionClass($indexScope->index->document);
        foreach ($documentReflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $reflectionProperty) {
            foreach ($reflectionProperty->getAttributes() as $reflectionAttribute) {
                $attribute = $reflectionAttribute->newInstance();

                match ($attribute::class) {
                    Filterable::class => $settings->filterableAttributes[] = $reflectionProperty->getName(),
                    Searchable::class => $settings->searchableAttributes[] = $reflectionProperty->getName(),
                    Sortable::class => $settings->sortableAttributes[] = $reflectionProperty->getName(),
                    default => null,
                };
            }
        }

        foreach ($documentReflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $reflectionMethod) {
            $property = self::getterProperty($reflectionMethod);
            if (null === $property) {
                continue;
            }

            foreach ($reflectionMethod->getAttributes() as $reflectionAttribute) {
                $attribute = $reflectionAttribute->newInstance();

                match ($attribute::class) {
                    Filterable::class => $settings->filterableAttributes[] = $property,
                    Searchable::class => $settings->searchableAttributes[] = $property,
                    Sortable::class => $settings->sortableAttributes[] = $property,
                    default => null,
                };
            }
        }

        $settings->synonyms = $this->synonymResolver->resolve($indexScope);

        return $settings;
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
