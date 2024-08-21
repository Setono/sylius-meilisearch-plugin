<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\DataMapper\Product\Provider;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;

final class ProductPricesProvider implements ProductPricesProviderInterface
{
    /** We need to provide the price from the first valid variant, to reflect the default Sylius UI behavior */
    public function getPricesForChannel(ProductInterface $product, ChannelInterface $channel): ProductPrices
    {
        $enabledVariants = $product->getEnabledVariants();
        if ($enabledVariants->isEmpty()) {
            return new ProductPrices();
        }

        /** @var ProductVariantInterface $firstEnabledVariant */
        $firstEnabledVariant = $enabledVariants->first();
        $firstChannelPricing = $firstEnabledVariant->getChannelPricingForChannel($channel);
        if (null === $firstChannelPricing) {
            return new ProductPrices();
        }

        return new ProductPrices(
            price: $firstChannelPricing->getPrice(),
            originalPrice: $firstChannelPricing->getOriginalPrice(),
        );
    }
}
