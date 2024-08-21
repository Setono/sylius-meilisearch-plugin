<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\DataMapper\Product\Provider;

use PHPUnit\Framework\TestCase;
use Setono\SyliusMeilisearchPlugin\DataMapper\Product\Provider\ProductPrices;
use Setono\SyliusMeilisearchPlugin\DataMapper\Product\Provider\ProductPricesProvider;
use Setono\SyliusMeilisearchPlugin\Tests\Application\Entity\Product;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\ChannelPricing;
use Sylius\Component\Core\Model\ProductVariant;

final class ProductPricesProviderTest extends TestCase
{
    public function testItProvidesFirstPriceFromProductEnabledVariant(): void
    {
        $product = new Product();
        $channel = new Channel();
        $channel->setCode('channel_code');

        $disabledVariant = new ProductVariant();
        $disabledVariant->setEnabled(false);

        $enabledVariant = new ProductVariant();
        $firstChannelPricing = new ChannelPricing();
        $firstChannelPricing->setPrice(1000);
        $firstChannelPricing->setChannelCode('channel_code');
        $secondChannelPricing = new ChannelPricing();
        $secondChannelPricing->setPrice(2000);
        $secondChannelPricing->setOriginalPrice(3000);
        $secondChannelPricing->setChannelCode('second_channel_code');
        $enabledVariant->addChannelPricing($firstChannelPricing);
        $enabledVariant->addChannelPricing($secondChannelPricing);

        $product->addVariant($disabledVariant);
        $product->addVariant($enabledVariant);

        self::assertEquals(
            new ProductPrices(1000),
            ((new ProductPricesProvider())->getPricesForChannel($product, $channel)),
        );
    }

    public function testItProvideNullValuesIfThereAreNoEnabledVariants(): void
    {
        $product = new Product();
        $channel = new Channel();

        $disabledVariant = new ProductVariant();
        $product->addVariant($disabledVariant);

        self::assertEquals(
            new ProductPrices(),
            ((new ProductPricesProvider())->getPricesForChannel($product, $channel)),
        );
    }

    public function testItProvideNullValuesIfThereAreNoPricesForGivenChannel(): void
    {
        $product = new Product();
        $channel = new Channel();
        $channel->setCode('another_code');

        $disabledVariant = new ProductVariant();
        $enabledVariant = new ProductVariant();
        $firstChannelPricing = new ChannelPricing();
        $firstChannelPricing->setPrice(1000);
        $firstChannelPricing->setChannelCode('channel_code');
        $enabledVariant->addChannelPricing($firstChannelPricing);

        $product->addVariant($disabledVariant);
        $product->addVariant($enabledVariant);

        self::assertEquals(
            new ProductPrices(),
            ((new ProductPricesProvider())->getPricesForChannel($product, $channel)),
        );
    }
}
