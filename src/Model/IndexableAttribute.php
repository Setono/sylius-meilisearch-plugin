<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Model;

use Sylius\Component\Product\Model\ProductAttributeInterface;

class IndexableAttribute extends IndexableSubject implements IndexableAttributeInterface
{
    protected ?ProductAttributeInterface $attribute = null;

    public function getAttribute(): ?ProductAttributeInterface
    {
        return $this->attribute;
    }

    public function setAttribute(?ProductAttributeInterface $attribute): void
    {
        $this->attribute = $attribute;
    }

    public function getCode(): ?string
    {
        return $this->attribute?->getCode();
    }
}
