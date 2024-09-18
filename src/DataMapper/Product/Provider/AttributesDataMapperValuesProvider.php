<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\DataMapper\Product\Provider;

use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScope;
use Sylius\Component\Core\Model\ProductInterface;
use Webmozart\Assert\Assert;

final class AttributesDataMapperValuesProvider implements DataMapperValuesProviderInterface
{
    public function provide(IndexableInterface $source, IndexScope $indexScope): array
    {
        Assert::isInstanceOf($source, ProductInterface::class);
        Assert::notNull($indexScope->localeCode);

        $attributes = [];

        foreach ($source->getAttributesByLocale($indexScope->localeCode, $indexScope->localeCode, $indexScope->localeCode) as $attribute) {
            $attributes[(string) $attribute->getAttribute()?->getCode()] = (string) $attribute->getValue();
        }

        return $attributes;
    }
}
