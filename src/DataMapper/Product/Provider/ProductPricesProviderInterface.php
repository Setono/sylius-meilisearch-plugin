<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\DataMapper\Product\Provider;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;

interface ProductPricesProviderInterface
{
    public function getPricesForChannel(ProductInterface $product, ChannelInterface $channel): ProductPrices;
}
