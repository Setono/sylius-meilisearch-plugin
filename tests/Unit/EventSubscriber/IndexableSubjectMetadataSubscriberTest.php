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
use Setono\SyliusMeilisearchPlugin\EventSubscriber\IndexableSubjectMetadataSubscriber;
use Setono\SyliusMeilisearchPlugin\Model\IndexableAttribute;
use Setono\SyliusMeilisearchPlugin\Model\IndexableOption;
use Setono\SyliusMeilisearchPlugin\Model\IndexableSubject;
use Setono\SyliusMeilisearchPlugin\Repository\IndexableAttributeRepositoryInterface;
use Setono\SyliusMeilisearchPlugin\Repository\IndexableOptionRepositoryInterface;
use Setono\SyliusMeilisearchPlugin\Tests\Application\Entity\Product as ProductEntity;
use Sylius\Component\Product\Model\ProductAttributeInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\DependencyInjection\Container;

/**
 * @covers \Setono\SyliusMeilisearchPlugin\EventSubscriber\IndexableSubjectMetadataSubscriber
 */
final class IndexableSubjectMetadataSubscriberTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_merges_configured_attributes_and_options_into_the_metadata(): void
    {
        $metadata = new Metadata(ProductDocument::class);

        $this->subscriber(
            attributes: [
                self::attribute('color', facetable: true, facetPosition: 3),
                self::attribute('material', searchable: true, filterable: true),
            ],
            options: [
                self::option('t_shirt_size', facetable: true),
            ],
            storageTypes: [
                'color' => 'json',
                'material' => 'text',
            ],
        )->onMetadataCreated(new MetadataCreated($metadata, self::index()));

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

        $this->subscriber(
            attributes: [
                self::attribute('eco_friendly', facetable: true),
                self::attribute('weight', facetable: true),
                self::attribute('length', facetable: true),
            ],
            options: [],
            storageTypes: [
                'eco_friendly' => 'boolean',
                'weight' => 'integer',
                'length' => 'float',
            ],
        )->onMetadataCreated(new MetadataCreated($metadata, self::index()));

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

        $this->subscriber(
            attributes: [
                self::attribute('production_date', searchable: true, facetable: true),
            ],
            options: [],
            storageTypes: [
                'production_date' => 'date',
            ],
        )->onMetadataCreated(new MetadataCreated($metadata, self::index()));

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

        $this->subscriber(
            attributes: [self::attribute('deleted_attribute', searchable: true)],
            options: [],
            storageTypes: [],
        )->onMetadataCreated(new MetadataCreated($metadata, self::index()));

        self::assertSame([], $metadata->dynamicFields);
        self::assertSame([], $metadata->searchableAttributes);
    }

    /**
     * @test
     */
    public function it_skips_codes_with_unsafe_characters(): void
    {
        $metadata = new Metadata(ProductDocument::class);

        $this->subscriber(
            attributes: [],
            options: [self::option('evil = "code"', facetable: true)],
            storageTypes: [],
        )->onMetadataCreated(new MetadataCreated($metadata, self::index()));

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

        $this->subscriber(
            attributes: [self::attribute('color', facetable: true)],
            options: [],
            storageTypes: ['color' => 'json'],
        )->onMetadataCreated(new MetadataCreated($metadata, self::index()));

        self::assertSame([], $metadata->dynamicFields);
        self::assertArrayNotHasKey('attr_color', $metadata->facetableAttributes);
    }

    /**
     * @test
     */
    public function it_does_nothing_without_index_context_or_for_ineligible_indexes(): void
    {
        $attributeRepository = $this->prophesize(IndexableAttributeRepositoryInterface::class);
        $attributeRepository->findEnabledByIndex(Argument::any())->shouldNotBeCalled();

        $optionRepository = $this->prophesize(IndexableOptionRepositoryInterface::class);
        $optionRepository->findEnabledByIndex(Argument::any())->shouldNotBeCalled();

        $subscriber = new IndexableSubjectMetadataSubscriber(
            $attributeRepository->reveal(),
            $optionRepository->reveal(),
            $this->prophesize(RepositoryInterface::class)->reveal(),
            new NullLogger(),
        );

        // no index context
        $subscriber->onMetadataCreated(new MetadataCreated(new Metadata(ProductDocument::class)));

        // an index without product(ish) entities
        $subscriber->onMetadataCreated(new MetadataCreated(
            new Metadata(TaxonDocument::class),
            new Index('taxons', TaxonDocument::class, [], new Container()),
        ));

        // an index that opted out of dynamic fields
        $subscriber->onMetadataCreated(new MetadataCreated(
            new Metadata(ProductDocument::class),
            new Index('autocomplete', ProductDocument::class, [ProductEntity::class], new Container(), dynamicFields: false),
        ));
    }

    /**
     * @param list<IndexableAttribute> $attributes
     * @param list<IndexableOption> $options
     * @param array<string, string> $storageTypes storage types indexed by attribute code
     */
    private function subscriber(array $attributes, array $options, array $storageTypes): IndexableSubjectMetadataSubscriber
    {
        $indexableAttributeRepository = $this->prophesize(IndexableAttributeRepositoryInterface::class);
        $indexableAttributeRepository->findEnabledByIndex('products')->willReturn($attributes);

        $indexableOptionRepository = $this->prophesize(IndexableOptionRepositoryInterface::class);
        $indexableOptionRepository->findEnabledByIndex('products')->willReturn($options);

        $productAttributes = [];
        foreach ($storageTypes as $code => $storageType) {
            $attribute = $this->prophesize(ProductAttributeInterface::class);
            $attribute->getCode()->willReturn($code);
            $attribute->getStorageType()->willReturn($storageType);

            $productAttributes[] = $attribute->reveal();
        }

        $productAttributeRepository = $this->prophesize(RepositoryInterface::class);
        $productAttributeRepository->findBy(Argument::type('array'))->willReturn($productAttributes);

        return new IndexableSubjectMetadataSubscriber(
            $indexableAttributeRepository->reveal(),
            $indexableOptionRepository->reveal(),
            $productAttributeRepository->reveal(),
            new NullLogger(),
        );
    }

    private static function index(): Index
    {
        return new Index('products', ProductDocument::class, [ProductEntity::class], new Container());
    }

    private static function attribute(
        string $code,
        bool $searchable = false,
        bool $filterable = false,
        bool $facetable = false,
        int $facetPosition = 0,
    ): IndexableAttribute {
        $row = new IndexableAttribute();
        self::configure($row, $code, $searchable, $filterable, $facetable, $facetPosition);

        return $row;
    }

    private static function option(
        string $code,
        bool $searchable = false,
        bool $filterable = false,
        bool $facetable = false,
        int $facetPosition = 0,
    ): IndexableOption {
        $row = new IndexableOption();
        self::configure($row, $code, $searchable, $filterable, $facetable, $facetPosition);

        return $row;
    }

    private static function configure(
        IndexableSubject $row,
        string $code,
        bool $searchable,
        bool $filterable,
        bool $facetable,
        int $facetPosition,
    ): void {
        $row->setCode($code);
        $row->setSearchable($searchable);
        $row->setFilterable($filterable);
        $row->setFacetable($facetable);
        $row->setFacetPosition($facetPosition);
        $row->addIndex('products');
        $row->setEnabled(true);
    }
}
