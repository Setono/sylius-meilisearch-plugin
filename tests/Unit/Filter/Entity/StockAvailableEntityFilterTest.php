<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\Filter\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\Document\Product as ProductDocument;
use Setono\SyliusMeilisearchPlugin\Filter\Entity\StockAvailableEntityFilter;
use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScope;
use Setono\SyliusMeilisearchPlugin\Tests\Application\Entity\Product;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Symfony\Component\DependencyInjection\Container;

/**
 * @covers \Setono\SyliusMeilisearchPlugin\Filter\Entity\StockAvailableEntityFilter
 */
final class StockAvailableEntityFilterTest extends TestCase
{
    use ProphecyTrait;

    private function indexScope(): IndexScope
    {
        return new IndexScope(new Index('products', ProductDocument::class, [Product::class], new Container()));
    }

    private function variant(bool $tracked, int $onHand, int $onHold = 0): ProductVariantInterface
    {
        $variant = $this->prophesize(ProductVariantInterface::class);
        $variant->isTracked()->willReturn($tracked);
        $variant->getOnHand()->willReturn($onHand);
        $variant->getOnHold()->willReturn($onHold);

        return $variant->reveal();
    }

    private function product(ProductVariantInterface ...$variants): ProductInterface&IndexableInterface
    {
        $product = $this->prophesize(ProductInterface::class);
        $product->willImplement(IndexableInterface::class);
        $product->getVariants()->willReturn(new ArrayCollection($variants));

        /** @var ProductInterface&IndexableInterface $revealed */
        $revealed = $product->reveal();

        return $revealed;
    }

    /**
     * @test
     */
    public function it_keeps_a_product_with_an_in_stock_variant(): void
    {
        $filter = new StockAvailableEntityFilter('products');

        self::assertTrue($filter->filter(
            $this->product($this->variant(tracked: true, onHand: 5)),
            new ProductDocument(),
            $this->indexScope(),
        ));
    }

    /**
     * @test
     */
    public function it_keeps_a_product_with_an_untracked_variant(): void
    {
        $filter = new StockAvailableEntityFilter('products');

        self::assertTrue($filter->filter(
            $this->product($this->variant(tracked: false, onHand: 0)),
            new ProductDocument(),
            $this->indexScope(),
        ));
    }

    /**
     * @test
     */
    public function it_filters_out_a_product_whose_variants_are_all_tracked_and_out_of_stock(): void
    {
        $filter = new StockAvailableEntityFilter('products');

        self::assertFalse($filter->filter(
            $this->product(
                $this->variant(tracked: true, onHand: 3, onHold: 3),
                $this->variant(tracked: true, onHand: 0),
            ),
            new ProductDocument(),
            $this->indexScope(),
        ));
    }

    /**
     * @test
     */
    public function it_filters_out_a_product_without_variants(): void
    {
        $filter = new StockAvailableEntityFilter('products');

        self::assertFalse($filter->filter($this->product(), new ProductDocument(), $this->indexScope()));
    }
}
