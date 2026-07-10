<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Resolver\Indexable;

use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
use Sylius\Component\Core\Model\ChannelPricingInterface;

/**
 * A change to a channel price resolves to the product that owns it, so price changes are reflected
 * in search and autocomplete without waiting for a full reindex.
 */
final class ChannelPricingIndexableEntityResolver implements IndexableEntityResolverInterface
{
    public function resolve(object $entity): iterable
    {
        if (!$entity instanceof ChannelPricingInterface) {
            return;
        }

        $variant = $entity->getProductVariant();
        $product = $variant?->getProduct();

        if ($product instanceof IndexableInterface) {
            yield $product;
        }
    }
}
