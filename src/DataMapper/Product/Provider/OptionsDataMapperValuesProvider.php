<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\DataMapper\Product\Provider;

use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScope;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Product\Model\ProductOptionValueInterface;
use Webmozart\Assert\Assert;

final class OptionsDataMapperValuesProvider implements DataMapperValuesProviderInterface
{
    public function provide(IndexableInterface $source, IndexScope $indexScope): array
    {
        Assert::isinstanceOf($source, ProductInterface::class);

        /** @var array<string, list<string>> $options */
        $options = [];

        /** @var ProductVariantInterface $variant */
        foreach ($source->getEnabledVariants() as $variant) {
            /** @var ProductOptionValueInterface $optionValue */
            foreach ($variant->getOptionValues() as $optionValue) {
                $option = $optionValue->getOptionCode();
                if ($option === null) {
                    continue;
                }

                $options[$option][] = (string) $optionValue->getValue();
            }
        }

        foreach ($options as $option => $values) {
            $options[$option] = array_values(array_unique($values));
        }

        return $options;
    }
}
