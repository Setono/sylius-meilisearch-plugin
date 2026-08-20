<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\EventSubscriber;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\NullLogger;
use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\Metadata;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\Searchable;
use Setono\SyliusMeilisearchPlugin\Document\Product as ProductDocument;
use Setono\SyliusMeilisearchPlugin\Document\Taxon as TaxonDocument;
use Setono\SyliusMeilisearchPlugin\Event\MetadataCreated;
use Setono\SyliusMeilisearchPlugin\EventSubscriber\IndexableAttributeMetadataSubscriber;
use Setono\SyliusMeilisearchPlugin\Model\IndexableAttribute;
use Setono\SyliusMeilisearchPlugin\Model\IndexableAttributeInterface;
use Setono\SyliusMeilisearchPlugin\Repository\IndexableAttributeRepositoryInterface;
use Sylius\Component\Product\Model\ProductAttributeInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\DependencyInjection\Container;

/**
 * @covers \Setono\SyliusMeilisearchPlugin\EventSubscriber\IndexableAttributeMetadataSubscriber
 */
final class IndexableAttributeMetadataSubscriberTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_merges_configured_attributes_and_options_into_the_metadata(): void
    {
        $metadata = new Metadata(ProductDocument::class);

        $this->subscriber([
            self::row(IndexableAttributeInterface::TYPE_ATTRIBUTE, 'color', facetable: true, facetPosition: 3),
            self::row(IndexableAttributeInterface::TYPE_ATTRIBUTE, 'material', searchable: true, filterable: true),
            self::row(IndexableAttributeInterface::TYPE_OPTION, 't_shirt_size', facetable: true),
        ], [
            'color' => 'json',
            'material' => 'text',
        ])->onMetadataCreated(new MetadataCreated($metadata, self::index()));

        // color: facetable json attribute
        self::assertArrayHasKey('attr_color', $metadata->dynamicFields);
        self::assertSame('array', $metadata->facetableAttributes['attr_color']->type);
        self::assertSame(3, $metadata->facetableAttributes['attr_color']->position);
        // a facetable field MUST also be filterable, or the search page errors
        self::assertArrayHasKey('attr_color', $metadata->filterableAttributes);
        self::assertArrayNotHasKey('attr_color', $metadata->searchableAttributes);

        // material: searchable + filterable text attribute
        self::assertArrayHasKey('attr_material', $metadata->searchableAttributes);
        self::assertArrayHasKey('attr_material', $metadata->filterableAttributes);
        self::assertArrayNotHasKey('attr_material', $metadata->facetableAttributes);

        // option: always an array facet
        self::assertArrayHasKey('opt_t_shirt_size', $metadata->dynamicFields);
        self::assertSame('array', $metadata->facetableAttributes['opt_t_shirt_size']->type);
        self::assertArrayHasKey('opt_t_shirt_size', $metadata->filterableAttributes);
    }

    /**
     * @test
     */
    public function it_coerces_storage_types(): void
    {
        $metadata = new Metadata(ProductDocument::class);

        $this->subscriber([
            self::row(IndexableAttributeInterface::TYPE_ATTRIBUTE, 'eco_friendly', facetable: true),
            self::row(IndexableAttributeInterface::TYPE_ATTRIBUTE, 'weight', facetable: true),
            self::row(IndexableAttributeInterface::TYPE_ATTRIBUTE, 'length', facetable: true),
        ], [
            'eco_friendly' => 'boolean',
            'weight' => 'integer',
            'length' => 'float',
        ])->onMetadataCreated(new MetadataCreated($metadata, self::index()));

        self::assertSame('bool', $metadata->facetableAttributes['attr_eco_friendly']->type);
        // integers become floats because the search side has no filter builder for the 'int' type
        self::assertSame('float', $metadata->facetableAttributes['attr_weight']->type);
        self::assertSame('float', $metadata->facetableAttributes['attr_length']->type);
    }

    /**
     * @test
     */
    public function it_does_not_create_a_facet_for_date_attributes(): void
    {
        $metadata = new Metadata(ProductDocument::class);

        $this->subscriber([
            self::row(IndexableAttributeInterface::TYPE_ATTRIBUTE, 'production_date', searchable: true, facetable: true),
        ], [
            'production_date' => 'date',
        ])->onMetadataCreated(new MetadataCreated($metadata, self::index()));

        self::assertArrayNotHasKey('attr_production_date', $metadata->facetableAttributes);
        // the other roles are still applied
        self::assertArrayHasKey('attr_production_date', $metadata->searchableAttributes);
        self::assertArrayHasKey('attr_production_date', $metadata->filterableAttributes);
    }

    /**
     * @test
     */
    public function it_skips_attributes_that_no_longer_exist(): void
    {
        $metadata = new Metadata(ProductDocument::class);

        $this->subscriber([
            self::row(IndexableAttributeInterface::TYPE_ATTRIBUTE, 'deleted_attribute', searchable: true),
        ], [])->onMetadataCreated(new MetadataCreated($metadata, self::index()));

        self::assertSame([], $metadata->dynamicFields);
        self::assertSame([], $metadata->searchableAttributes);
    }

    /**
     * @test
     */
    public function it_skips_codes_with_unsafe_characters(): void
    {
        $metadata = new Metadata(ProductDocument::class);

        $this->subscriber([
            self::row(IndexableAttributeInterface::TYPE_OPTION, 'evil = "code"', facetable: true),
        ], [])->onMetadataCreated(new MetadataCreated($metadata, self::index()));

        self::assertSame([], $metadata->dynamicFields);
        self::assertSame([], $metadata->facetableAttributes);
    }

    /**
     * @test
     */
    public function it_skips_fields_that_collide_with_existing_metadata(): void
    {
        $metadata = new Metadata(ProductDocument::class);
        $metadata->searchableAttributes['attr_color'] = new Searchable('attr_color');

        $this->subscriber([
            self::row(IndexableAttributeInterface::TYPE_ATTRIBUTE, 'color', facetable: true),
        ], [
            'color' => 'json',
        ])->onMetadataCreated(new MetadataCreated($metadata, self::index()));

        self::assertSame([], $metadata->dynamicFields);
        self::assertArrayNotHasKey('attr_color', $metadata->facetableAttributes);
    }

    /**
     * @test
     */
    public function it_does_nothing_without_index_context_or_for_non_product_documents(): void
    {
        $repository = $this->prophesize(IndexableAttributeRepositoryInterface::class);
        $repository->findEnabledByIndex(Argument::any())->shouldNotBeCalled();

        $attributeRepository = $this->prophesize(RepositoryInterface::class);

        $subscriber = new IndexableAttributeMetadataSubscriber(
            $repository->reveal(),
            $attributeRepository->reveal(),
            new NullLogger(),
        );

        $subscriber->onMetadataCreated(new MetadataCreated(new Metadata(ProductDocument::class)));
        $subscriber->onMetadataCreated(new MetadataCreated(
            new Metadata(TaxonDocument::class),
            new Index('taxons', TaxonDocument::class, [], new Container()),
        ));
    }

    /**
     * @param list<IndexableAttributeInterface> $rows
     * @param array<string, string> $storageTypes storage types indexed by attribute code
     */
    private function subscriber(array $rows, array $storageTypes): IndexableAttributeMetadataSubscriber
    {
        $repository = $this->prophesize(IndexableAttributeRepositoryInterface::class);
        $repository->findEnabledByIndex('products')->willReturn($rows);

        $attributes = [];
        foreach ($storageTypes as $code => $storageType) {
            $attribute = $this->prophesize(ProductAttributeInterface::class);
            $attribute->getCode()->willReturn($code);
            $attribute->getStorageType()->willReturn($storageType);

            $attributes[] = $attribute->reveal();
        }

        $attributeRepository = $this->prophesize(RepositoryInterface::class);
        $attributeRepository->findBy(Argument::type('array'))->willReturn($attributes);

        return new IndexableAttributeMetadataSubscriber(
            $repository->reveal(),
            $attributeRepository->reveal(),
            new NullLogger(),
        );
    }

    private static function index(): Index
    {
        return new Index('products', ProductDocument::class, [], new Container());
    }

    private static function row(
        string $type,
        string $code,
        bool $searchable = false,
        bool $filterable = false,
        bool $facetable = false,
        int $facetPosition = 0,
    ): IndexableAttributeInterface {
        $row = new IndexableAttribute();
        $row->setType($type);
        $row->setCode($code);
        $row->setSearchable($searchable);
        $row->setFilterable($filterable);
        $row->setFacetable($facetable);
        $row->setFacetPosition($facetPosition);
        $row->addIndex('products');
        $row->setEnabled(true);

        return $row;
    }
}
