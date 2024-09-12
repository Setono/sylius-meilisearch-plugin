<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\DataMapper\Product\Provider;

use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
use Webmozart\Assert\Assert;

final class AttributesDataMapperValuesProvider implements DataMapperValuesProviderInterface
{
    public function provide(IndexableInterface $source, array $context = []): array
    {
        Assert::keyExists($context, 'locale_code');
        Assert::string($context['locale_code']);

        $attributes = [];

        foreach ($source->getAttributesByLocale($context['locale_code'], $context['locale_code'], $context['locale_code']) as $attribute) {
            $attributes[(string) $attribute->getAttribute()?->getCode()] = (string) $attribute->getValue();
        }

        return $attributes;
    }
}
