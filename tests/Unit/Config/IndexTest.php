<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\Config;

use PHPUnit\Framework\TestCase;
use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\Document\Product as ProductDocument;
use Setono\SyliusMeilisearchPlugin\Document\Taxon as TaxonDocument;
use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
use Setono\SyliusMeilisearchPlugin\Tests\Application\Entity\Product as ProductEntity;
use Setono\SyliusMeilisearchPlugin\Tests\Application\Entity\Taxon as TaxonEntity;
use Sylius\Component\Core\Model\ProductVariant;
use Symfony\Component\DependencyInjection\Container;

final class IndexableVariant extends ProductVariant implements IndexableInterface
{
    public function getDocumentIdentifier(): ?string
    {
        return null === $this->getId() ? null : (string) $this->getId();
    }
}

/**
 * @covers \Setono\SyliusMeilisearchPlugin\Config\Index
 */
final class IndexTest extends TestCase
{
    /**
     * @test
     */
    public function it_supports_dynamic_fields_when_it_indexes_products(): void
    {
        $index = new Index('products', ProductDocument::class, [ProductEntity::class], new Container());

        self::assertTrue($index->supportsDynamicFields());
    }

    /**
     * @test
     */
    public function it_supports_dynamic_fields_when_it_indexes_product_variants(): void
    {
        $index = new Index('variants', ProductDocument::class, [IndexableVariant::class], new Container());

        self::assertTrue($index->supportsDynamicFields());
    }

    /**
     * @test
     */
    public function it_does_not_support_dynamic_fields_when_it_indexes_neither_products_nor_variants(): void
    {
        $index = new Index('taxons', TaxonDocument::class, [TaxonEntity::class], new Container());

        self::assertFalse($index->supportsDynamicFields());
    }

    /**
     * @test
     */
    public function it_does_not_support_dynamic_fields_when_the_index_opted_out(): void
    {
        $index = new Index('products', ProductDocument::class, [ProductEntity::class], new Container(), dynamicFields: false);

        self::assertFalse($index->supportsDynamicFields());
    }
}
