<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\DataMapper\Product\Provider;

use Setono\SyliusMeilisearchPlugin\Document\Document;
use Webmozart\Assert\Assert;

final class ReflectionAttributeValuesProvider implements ReflectionAttributeValuesProviderInterface
{
    public function __construct(private readonly string $attributeType)
    {
    }

    public function provide(
        \ReflectionAttribute $reflectionAttribute,
        Document $target,
        string $propertyName,
        array $attributes,
    ): array {
        $attribute = $reflectionAttribute->newInstance();

        Assert::isInstanceOf($attribute, $this->attributeType);
        Assert::false(!isset($target->{$propertyName}) || !is_array($target->{$propertyName}));

        $values = [];

        foreach ($attribute->codes as $code) {
            if (!isset($attributes[$code])) {
                continue;
            }

            $values[] = $attributes[$code];
        }

        return $values;
    }
}
