<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\DataMapper\Product\Setter;

use Setono\SyliusMeilisearchPlugin\Document\Document;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\MetadataFactoryInterface;

final class DocumentOptionsValuesSetter implements DocumentPropertyValuesSetterInterface
{
    public function __construct(private readonly MetadataFactoryInterface $metadataFactory)
    {
    }

    public function setFor(Document $target, array $attributes): void
    {
        $metadata = $this->metadataFactory->getMetadataFor($target);

        foreach ($metadata->mappedProductOptions as $property => $optionCodes) {
            $values = [];
            /** @var string $code */
            foreach ($optionCodes as $code) {
                if (!isset($attributes[$code])) {
                    continue;
                }

                /** @var array $value */
                $value = $attributes[$code];
                $values[] = $value;
            }

            $values = array_values(array_unique(array_merge(...$values)));

            /** @var array $currentValues */
            $currentValues = $target->{$property};
            $target->{$property} = array_merge($currentValues, $values);
        }
    }
}
