<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\DataMapper\Product\Provider;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Setono\SyliusMeilisearchPlugin\DataMapper\Product\Provider\ProductPricesProvider;
use Setono\SyliusMeilisearchPlugin\DataMapper\Product\Provider\ProductPricesProviderInterface;
use Setono\SyliusMeilisearchPlugin\Tests\Application\Entity\Product;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\ChannelPricing;
use Sylius\Component\Core\Model\ProductVariant;
use Sylius\Component\Product\Resolver\ProductVariantResolverInterface;

final class ProductPricesProviderTest extends TestCase
{
    private MockObject&ProductVariantResolverInterface $productVariantResolver;

    protected function setUp(): void
    {
        $this->productVariantResolver = $this->createMock(ProductVariantResolverInterface::class);
    }

    public function test_it_provides_prices_for_a_product(): void
    {
        $product = new Product();
        $channel = new Channel();
        $channel->setCode('MY_CHANNEL');

        $channelPricing = new ChannelPricing();
        $channelPricing->setChannelCode('MY_CHANNEL');
        $channelPricing->setPrice(1000);
        $channelPricing->setOriginalPrice(2000);

        $productVariant = new ProductVariant();
        $productVariant->addChannelPricing($channelPricing);

        $this->productVariantResolver->method('getVariant')->willReturn($productVariant);

        $productPrices = $this->getTestSubject()->getPricesForChannel($product, $channel);

        $this->assertEquals(1000, $productPrices->price);
        $this->assertEquals(2000, $productPrices->originalPrice);
    }

    public function test_it_returns_empty_object_if_no_variant_is_found(): void
    {
        $product = new Product();
        $channel = new Channel();

        $this->productVariantResolver->method('getVariant')->willReturn(null);

        $productPrices = $this->getTestSubject()->getPricesForChannel($product, $channel);
        self::assertNull($productPrices->price);
        self::assertNull($productPrices->originalPrice);
    }

    public function test_it_returns_empty_object_if_no_channel_pricing_is_found(): void
    {
        $product = new Product();
        $channel = new Channel();
        $channel->setCode('MY_CHANNEL');

        $productVariant = new ProductVariant();

        $this->productVariantResolver->method('getVariant')->willReturn($productVariant);

        $productPrices = $this->getTestSubject()->getPricesForChannel($product, $channel);
        self::assertNull($productPrices->price);
        self::assertNull($productPrices->originalPrice);
    }

    private function getTestSubject(): ProductPricesProviderInterface
    {
        return new ProductPricesProvider($this->productVariantResolver);
    }
}
