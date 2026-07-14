<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\Resolver\Indexable;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
use Setono\SyliusMeilisearchPlugin\Resolver\Indexable\ChannelPricingIndexableEntityResolver;
use Setono\SyliusMeilisearchPlugin\Resolver\Indexable\CompositeIndexableEntityResolver;
use Setono\SyliusMeilisearchPlugin\Resolver\Indexable\IndexableEntityResolver;
use Setono\SyliusMeilisearchPlugin\Resolver\Indexable\ProductVariantIndexableEntityResolver;
use Setono\SyliusMeilisearchPlugin\Resolver\Indexable\TranslationIndexableEntityResolver;
use Sylius\Component\Core\Model\ChannelPricingInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Resource\Model\TranslationInterface;

/**
 * @covers \Setono\SyliusMeilisearchPlugin\Resolver\Indexable\ChannelPricingIndexableEntityResolver
 * @covers \Setono\SyliusMeilisearchPlugin\Resolver\Indexable\CompositeIndexableEntityResolver
 * @covers \Setono\SyliusMeilisearchPlugin\Resolver\Indexable\IndexableEntityResolver
 * @covers \Setono\SyliusMeilisearchPlugin\Resolver\Indexable\ProductVariantIndexableEntityResolver
 * @covers \Setono\SyliusMeilisearchPlugin\Resolver\Indexable\TranslationIndexableEntityResolver
 */
final class IndexableEntityResolverTest extends TestCase
{
    use ProphecyTrait;

    private function composite(): CompositeIndexableEntityResolver
    {
        $composite = new CompositeIndexableEntityResolver();
        $composite->add(new IndexableEntityResolver());
        $composite->add(new TranslationIndexableEntityResolver());
        $composite->add(new ChannelPricingIndexableEntityResolver());
        $composite->add(new ProductVariantIndexableEntityResolver());

        return $composite;
    }

    /**
     * @return ProductInterface&IndexableInterface
     */
    private function indexableProduct(): ProductInterface
    {
        $product = $this->prophesize(ProductInterface::class);
        $product->willImplement(IndexableInterface::class);

        /** @var ProductInterface&IndexableInterface $revealed */
        $revealed = $product->reveal();

        return $revealed;
    }

    /**
     * @param iterable<IndexableInterface> $resolved
     *
     * @return list<IndexableInterface>
     */
    private function toList(iterable $resolved): array
    {
        return iterator_to_array((function () use ($resolved) {
            yield from $resolved;
        })(), false);
    }

    /**
     * @test
     */
    public function it_resolves_an_indexable_entity_to_itself(): void
    {
        $product = $this->indexableProduct();

        self::assertSame([$product], $this->toList($this->composite()->resolve($product)));
    }

    /**
     * @test
     */
    public function it_resolves_a_translation_to_its_translatable(): void
    {
        $product = $this->indexableProduct();

        $translation = $this->prophesize(TranslationInterface::class);
        $translation->getTranslatable()->willReturn($product);

        self::assertSame([$product], $this->toList($this->composite()->resolve($translation->reveal())));
    }

    /**
     * @test
     */
    public function it_resolves_a_channel_pricing_to_its_product(): void
    {
        $product = $this->indexableProduct();

        $variant = $this->prophesize(ProductVariantInterface::class);
        $variant->getProduct()->willReturn($product);

        $channelPricing = $this->prophesize(ChannelPricingInterface::class);
        $channelPricing->getProductVariant()->willReturn($variant->reveal());

        self::assertSame([$product], $this->toList($this->composite()->resolve($channelPricing->reveal())));
    }

    /**
     * @test
     */
    public function it_resolves_a_variant_to_its_product(): void
    {
        $product = $this->indexableProduct();

        $variant = $this->prophesize(ProductVariantInterface::class);
        $variant->getProduct()->willReturn($product);

        self::assertSame([$product], $this->toList($this->composite()->resolve($variant->reveal())));
    }

    /**
     * @test
     */
    public function it_resolves_an_unrelated_object_to_nothing(): void
    {
        self::assertSame([], $this->toList($this->composite()->resolve(new \stdClass())));
    }
}
