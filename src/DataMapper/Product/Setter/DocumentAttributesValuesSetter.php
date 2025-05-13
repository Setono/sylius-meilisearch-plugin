<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\DataMapper\Product\Setter;

use Setono\SyliusMeilisearchPlugin\Document\Document;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\MetadataFactoryInterface;

final class DocumentAttributesValuesSetter implements DocumentPropertyValuesSetterInterface
{
    public function __construct(private readonly MetadataFactoryInterface $metadataFactory)
    {
    }

    public function setFor(Document $target, array $attributes): void
    {
        $metadata = $this->metadataFactory->getMetadataFor($target);

        foreach ($metadata->mappedProductAttributes as $mappedProductAttribute) {
            $values = [];
            foreach ($mappedProductAttribute->codes as $code) {
                if (!isset($attributes[$code])) {
                    continue;
                }

                $values[] = (array) $attributes[$code];
            }

            if ([] === $values) {
                continue;
            }

            $values = array_merge(...$values);

            if ($mappedProductAttribute->isPropertyTypeScalar()) {
                $target->{$mappedProductAttribute->property} = $values[0];
            } else {
                $target->{$mappedProductAttribute->property} = array_merge((array) $target->{$mappedProductAttribute->property}, $values);
            }
        }
    }
}
