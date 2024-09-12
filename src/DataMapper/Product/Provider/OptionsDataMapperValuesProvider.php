<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\DataMapper\Product\Provider;

use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;

final class OptionsDataMapperValuesProvider implements DataMapperValuesProviderInterface
{
    public function provide(IndexableInterface $source, array $context = []): array
    {
        /** @var array<string, list<string>> $options */
        $options = [];

        foreach ($source->getEnabledVariants() as $variant) {
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
