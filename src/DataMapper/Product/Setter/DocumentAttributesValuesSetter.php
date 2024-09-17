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

        foreach ($metadata->getMappedProductAttributes() as $property => $codes) {
            $values = [];
            foreach ($codes as $code) {
                if (!isset($attributes[$code])) {
                    continue;
                }

                /** @var string $value */
                $value = $attributes[$code];
                $values[] = $value;
            }

            /** @var array $currentValues */
            $currentValues = $target->{$property};
            $target->{$property} = array_merge($currentValues, $values);
        }
    }
}
