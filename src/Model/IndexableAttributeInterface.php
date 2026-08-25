<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Model;

use Sylius\Component\Product\Model\ProductAttributeInterface;

/**
 * Configures a Sylius product attribute to be indexed in Meilisearch
 */
interface IndexableAttributeInterface extends IndexableSubjectInterface
{
    public function getAttribute(): ?ProductAttributeInterface;

    public function setAttribute(?ProductAttributeInterface $attribute): void;
}
