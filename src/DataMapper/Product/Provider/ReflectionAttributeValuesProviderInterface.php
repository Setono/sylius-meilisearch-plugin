<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\DataMapper\Product\Provider;

use Setono\SyliusMeilisearchPlugin\Document\Document;

interface ReflectionAttributeValuesProviderInterface
{
    public function provide(
        \ReflectionAttribute $reflectionAttribute,
        Document $target,
        string $propertyName,
        array $attributes,
    ): array;
}
