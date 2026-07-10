<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\DataMapper\Product;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\DataMapper\Product\PriceDataMapper;
use Setono\SyliusMeilisearchPlugin\DataMapper\Product\Provider\ProductPricesProviderInterface;
use Setono\SyliusMeilisearchPlugin\Document\Product as ProductDocument;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScope;
use Setono\SyliusMeilisearchPlugin\Tests\Application\Entity\Product;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Currency\Converter\CurrencyConverterInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class PriceDataMapperTest extends TestCase
{
    use ProphecyTrait;

    private function createDataMapper(): PriceDataMapper
    {
        return new PriceDataMapper(
            $this->prophesize(ChannelRepositoryInterface::class)->reveal(),
            $this->prophesize(CurrencyConverterInterface::class)->reveal(),
            $this->prophesize(ProductPricesProviderInterface::class)->reveal(),
        );
    }

    private function createIndexScope(?string $channelCode, ?string $currencyCode): IndexScope
    {
        $index = new Index('products', ProductDocument::class, [Product::class], new ContainerBuilder());

        return new IndexScope($index, $channelCode, 'en_US', $currencyCode);
    }

    /**
     * @test
     */
    public function it_does_not_support_a_scope_without_a_currency(): void
    {
        $dataMapper = $this->createDataMapper();

        self::assertFalse($dataMapper->supports(
            new Product(),
            new ProductDocument(),
            $this->createIndexScope('FASHION_WEB', null),
        ));
    }

    /**
     * @test
     */
    public function it_does_not_support_a_scope_without_a_channel(): void
    {
        $dataMapper = $this->createDataMapper();

        self::assertFalse($dataMapper->supports(
            new Product(),
            new ProductDocument(),
            $this->createIndexScope(null, 'USD'),
        ));
    }

    /**
     * @test
     */
    public function it_supports_a_scope_with_both_a_channel_and_a_currency(): void
    {
        $dataMapper = $this->createDataMapper();

        self::assertTrue($dataMapper->supports(
            new Product(),
            new ProductDocument(),
            $this->createIndexScope('FASHION_WEB', 'USD'),
        ));
    }
}
