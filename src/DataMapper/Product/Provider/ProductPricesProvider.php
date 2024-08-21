<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\DataMapper\Product\Provider;

use Setono\SyliusMeilisearchPlugin\DataMapper\Product\Provider\Exception\ProductPricesCannotBeProvidedException;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Product\Resolver\ProductVariantResolverInterface;

final class ProductPricesProvider implements ProductPricesProviderInterface
{
    public function __construct(
        private readonly ProductVariantResolverInterface $productVariantResolver,
    ) {
    }

    /** We need to provide the price from the first valid variant, to reflect the default Sylius UI behavior */
    public function getPricesForChannel(ProductInterface $product, ChannelInterface $channel): ProductPrices
    {
        /** @var ProductVariantInterface|null $variant */
        $variant = $this->productVariantResolver->getVariant($product);
        $channelPricing = $variant?->getChannelPricingForChannel($channel);
        $price = $channelPricing?->getPrice();

        if (null === $variant || null === $channelPricing || null === $price) {
            throw new ProductPricesCannotBeProvidedException();
        }

        return new ProductPrices(
            price: $price,
            originalPrice: $channelPricing->getOriginalPrice(),
        );
    }
}
