<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\DataMapper\Product\Provider;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Product\Resolver\ProductVariantResolverInterface;

final class ProductPricesProvider implements ProductPricesProviderInterface
{
    public function __construct(private readonly ProductVariantResolverInterface $productVariantResolver)
    {
    }

    public function getPricesForChannel(ProductInterface $product, ChannelInterface $channel): ProductPrices
    {
        $variant = $this->productVariantResolver->getVariant($product);
        if (!$variant instanceof ProductVariantInterface) {
            return new ProductPrices();
        }

        $channelPricing = $variant->getChannelPricingForChannel($channel);

        return new ProductPrices(
            price: $channelPricing?->getPrice(),
            originalPrice: $channelPricing?->getOriginalPrice(),
        );
    }
}
