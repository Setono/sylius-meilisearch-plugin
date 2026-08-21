<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\DataMapper\Product;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\DataMapper\Product\DynamicFieldsDataMapper;
use Setono\SyliusMeilisearchPlugin\DataMapper\Product\Provider\DataMapperValuesProviderInterface;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\DynamicField;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\Metadata;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\MetadataFactoryInterface;
use Setono\SyliusMeilisearchPlugin\Document\Product as ProductDocument;
use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScope;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Product\Model\ProductOptionValueInterface;
use Symfony\Component\DependencyInjection\Container;

/**
 * @covers \Setono\SyliusMeilisearchPlugin\DataMapper\Product\DynamicFieldsDataMapper
 */
final class DynamicFieldsDataMapperTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<DataMapperValuesProviderInterface> */
    private ObjectProphecy $attributesValuesProvider;

    /** @var ObjectProphecy<DataMapperValuesProviderInterface> */
    private ObjectProphecy $optionsValuesProvider;

    /**
     * @test
     */
    public function it_maps_dynamic_fields_into_the_attributes_bag(): void
    {
        $metadata = new Metadata(ProductDocument::class);
        $metadata->dynamicFields = [
            'attr_color' => new DynamicField('attr_color', DynamicField::SOURCE_ATTRIBUTE, 'color', 'array'),
            'attr_material' => new DynamicField('attr_material', DynamicField::SOURCE_ATTRIBUTE, 'material', 'array'),
            'attr_eco_friendly' => new DynamicField('attr_eco_friendly', DynamicField::SOURCE_ATTRIBUTE, 'eco_friendly', 'bool'),
            'attr_weight' => new DynamicField('attr_weight', DynamicField::SOURCE_ATTRIBUTE, 'weight', 'float'),
            'attr_missing' => new DynamicField('attr_missing', DynamicField::SOURCE_ATTRIBUTE, 'missing', 'array'),
            'opt_t_shirt_size' => new DynamicField('opt_t_shirt_size', DynamicField::SOURCE_OPTION, 't_shirt_size', 'array'),
        ];

        $mapper = $this->mapper($metadata);

        $sourceProphecy = $this->prophesize(ProductInterface::class);
        $sourceProphecy->willImplement(IndexableInterface::class);
        $source = $sourceProphecy->reveal();
        \assert($source instanceof IndexableInterface);
        $indexScope = self::indexScope();

        // the providers must be called once even though multiple fields share the source
        $this->attributesValuesProvider->provide($source, $indexScope)->shouldBeCalledOnce()->willReturn([
            'color' => ['Red', 'Blue'],
            'material' => 'Cotton',
            'eco_friendly' => true,
            'weight' => 5,
        ]);
        $this->optionsValuesProvider->provide($source, $indexScope)->shouldBeCalledOnce()->willReturn([
            't_shirt_size' => ['S', 'M'],
        ]);

        $document = new ProductDocument();
        $mapper->map($source, $document, $indexScope);

        self::assertSame([
            'attr_color' => ['Red', 'Blue'],
            // a scalar value of an 'array' field is wrapped so single and multi valued sources look the same
            'attr_material' => ['Cotton'],
            'attr_eco_friendly' => true,
            // ints become floats because the search side has no 'int' type
            'attr_weight' => 5.0,
            'opt_t_shirt_size' => ['S', 'M'],
        ], $document->dynamicFields);
    }

    /**
     * @test
     */
    public function it_maps_dynamic_fields_for_a_product_variant(): void
    {
        $metadata = new Metadata(ProductDocument::class);
        $metadata->dynamicFields = [
            'attr_material' => new DynamicField('attr_material', DynamicField::SOURCE_ATTRIBUTE, 'material', 'array'),
            'opt_t_shirt_size' => new DynamicField('opt_t_shirt_size', DynamicField::SOURCE_OPTION, 't_shirt_size', 'array'),
        ];

        $mapper = $this->mapper($metadata);

        $product = $this->prophesize(ProductInterface::class)->reveal();

        $optionValue = $this->prophesize(ProductOptionValueInterface::class);
        $optionValue->getOptionCode()->willReturn('t_shirt_size');
        $optionValue->getValue()->willReturn('M');

        $sourceProphecy = $this->prophesize(ProductVariantInterface::class);
        $sourceProphecy->willImplement(IndexableInterface::class);
        $sourceProphecy->getProduct()->willReturn($product);
        $sourceProphecy->getOptionValues()->willReturn(new ArrayCollection([$optionValue->reveal()]));
        $source = $sourceProphecy->reveal();
        \assert($source instanceof IndexableInterface);

        $indexScope = self::indexScope();

        // the attributes come from the parent product...
        $this->attributesValuesProvider->provide($product, $indexScope)->shouldBeCalledOnce()->willReturn([
            'material' => ['Cotton'],
        ]);
        // ...while the options come from the variant itself, not from the product wide provider
        $this->optionsValuesProvider->provide(Argument::cetera())->shouldNotBeCalled();

        $document = new ProductDocument();
        $mapper->map($source, $document, $indexScope);

        self::assertSame([
            'attr_material' => ['Cotton'],
            'opt_t_shirt_size' => ['M'],
        ], $document->dynamicFields);
    }

    /**
     * @test
     */
    public function it_skips_attribute_fields_for_a_variant_without_a_product(): void
    {
        $metadata = new Metadata(ProductDocument::class);
        $metadata->dynamicFields = [
            'attr_material' => new DynamicField('attr_material', DynamicField::SOURCE_ATTRIBUTE, 'material', 'array'),
            'opt_t_shirt_size' => new DynamicField('opt_t_shirt_size', DynamicField::SOURCE_OPTION, 't_shirt_size', 'array'),
        ];

        $mapper = $this->mapper($metadata);

        $optionValue = $this->prophesize(ProductOptionValueInterface::class);
        $optionValue->getOptionCode()->willReturn('t_shirt_size');
        $optionValue->getValue()->willReturn('M');

        $sourceProphecy = $this->prophesize(ProductVariantInterface::class);
        $sourceProphecy->willImplement(IndexableInterface::class);
        $sourceProphecy->getProduct()->willReturn(null);
        $sourceProphecy->getOptionValues()->willReturn(new ArrayCollection([$optionValue->reveal()]));
        $source = $sourceProphecy->reveal();
        \assert($source instanceof IndexableInterface);

        $this->attributesValuesProvider->provide(Argument::cetera())->shouldNotBeCalled();

        $document = new ProductDocument();
        $mapper->map($source, $document, self::indexScope());

        self::assertSame(['opt_t_shirt_size' => ['M']], $document->dynamicFields);
    }

    /**
     * @test
     */
    public function it_supports_products_with_dynamic_fields_and_a_locale(): void
    {
        $metadata = new Metadata(ProductDocument::class);
        $metadata->dynamicFields['attr_color'] = new DynamicField('attr_color', DynamicField::SOURCE_ATTRIBUTE, 'color', 'array');

        $mapper = $this->mapper($metadata);

        $sourceProphecy = $this->prophesize(ProductInterface::class);
        $sourceProphecy->willImplement(IndexableInterface::class);
        $source = $sourceProphecy->reveal();
        \assert($source instanceof IndexableInterface);

        self::assertTrue($mapper->supports($source, new ProductDocument(), self::indexScope()));
        self::assertFalse($mapper->supports($source, new ProductDocument(), self::indexScope(localeCode: null)));
    }

    /**
     * @test
     */
    public function it_does_not_support_documents_without_dynamic_fields(): void
    {
        $mapper = $this->mapper(new Metadata(ProductDocument::class));

        $sourceProphecy = $this->prophesize(ProductInterface::class);
        $sourceProphecy->willImplement(IndexableInterface::class);
        $source = $sourceProphecy->reveal();
        \assert($source instanceof IndexableInterface);

        self::assertFalse($mapper->supports($source, new ProductDocument(), self::indexScope()));
    }

    private function mapper(Metadata $metadata): DynamicFieldsDataMapper
    {
        $metadataFactory = $this->prophesize(MetadataFactoryInterface::class);
        $metadataFactory->getMetadataFor(new ProductDocument(), self::indexScope()->index)->willReturn($metadata);

        $this->attributesValuesProvider = $this->prophesize(DataMapperValuesProviderInterface::class);
        $this->optionsValuesProvider = $this->prophesize(DataMapperValuesProviderInterface::class);

        return new DynamicFieldsDataMapper(
            $metadataFactory->reveal(),
            $this->attributesValuesProvider->reveal(),
            $this->optionsValuesProvider->reveal(),
        );
    }

    private static function indexScope(?string $localeCode = 'en_US'): IndexScope
    {
        return new IndexScope(
            new Index('products', ProductDocument::class, [], new Container()),
            localeCode: $localeCode,
        );
    }
}
