<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Resolver\Indexable;

use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;

/**
 * A change to a variant resolves to the product that owns it. This covers stock changes, since a
 * variant's onHand/onHold live on the variant.
 */
final class ProductVariantIndexableEntityResolver implements IndexableEntityResolverInterface
{
    public function resolve(object $entity): iterable
    {
        if (!$entity instanceof ProductVariantInterface) {
            return;
        }

        $product = $entity->getProduct();

        if ($product instanceof IndexableInterface) {
            yield $product;
        }
    }
}
