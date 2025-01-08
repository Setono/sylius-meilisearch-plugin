<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Document\Metadata;

final class MappedProductAttribute
{
    public readonly string $propertyType;

    public function __construct(
        public readonly string $property,
        string $propertyType,
        /** @var non-empty-list<string> $codes */
        public readonly array $codes,
    ) {
        // todo use Symfony Type component instead
        if (!in_array($propertyType, ['string', 'int', 'float', 'bool', 'array', '?string', '?int', '?float', '?bool', '?array'], true)) {
            throw new \InvalidArgumentException(sprintf('Property type must be one of: string, int, float, bool, array, or null. Type given: %s', $propertyType));
        }

        $this->propertyType = $propertyType;
    }

    public function isPropertyTypeScalar(): bool
    {
        return in_array($this->propertyType, ['string', 'int', 'float', 'bool', '?string', '?int', '?float', '?bool'], true);
    }

    public function isPropertyTypeArray(): bool
    {
        return in_array($this->propertyType, ['array', '?array'], true);
    }
}
