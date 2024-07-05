<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Provider\Settings;

use Setono\SyliusMeilisearchPlugin\Document\Attribute\Filterable;
use Setono\SyliusMeilisearchPlugin\Document\Attribute\Searchable;
use Setono\SyliusMeilisearchPlugin\Document\Attribute\Sortable;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScope;
use Setono\SyliusMeilisearchPlugin\Settings\Settings;

final class SettingsProvider implements SettingsProviderInterface
{
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
                };
            }
        }

        return $settings;
    }
}
