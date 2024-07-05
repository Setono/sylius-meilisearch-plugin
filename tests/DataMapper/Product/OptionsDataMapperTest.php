<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\DataMapper\Product;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\DataMapper\Product\OptionsDataMapper;
use Setono\SyliusMeilisearchPlugin\Document\Attribute\MapProductOption;
use Setono\SyliusMeilisearchPlugin\Document\Product as BaseProduct;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScope;
use Setono\SyliusMeilisearchPlugin\Tests\Application\Entity\Product;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Product\Model\ProductOptionValueInterface;
use Symfony\Component\DependencyInjection\Container;

final class OptionsDataMapperTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_maps(): void
    {
        $optionValue1 = $this->prophesize(ProductOptionValueInterface::class);
        $optionValue1->getOptionCode()->willReturn('t_shirt_size');
        $optionValue1->getValue()->willReturn('L');

        $optionValue2 = $this->prophesize(ProductOptionValueInterface::class);
        $optionValue2->getOptionCode()->willReturn('dress_size');
        $optionValue2->getValue()->willReturn('Big');

        $optionValue3 = $this->prophesize(ProductOptionValueInterface::class);
        $optionValue3->getOptionCode()->willReturn('jeans_size');
        $optionValue3->getValue()->willReturn('31/34');

        $productVariant = $this->prophesize(ProductVariantInterface::class);
        $productVariant->getOptionValues()->willReturn(new ArrayCollection([
            $optionValue1->reveal(),
            $optionValue2->reveal(),
            $optionValue3->reveal(),
        ]));

        $product = $this->prophesize(Product::class);
        $product->getEnabledVariants()->willReturn(new ArrayCollection([$productVariant->reveal()]));

        $productDocument = new ProductDocument();

        $dataMapper = new OptionsDataMapper();
        $dataMapper->map($product->reveal(), $productDocument, new IndexScope(new Index('products', ProductDocument::class, [], new Container())));

        $this->assertSame(['L', 'Big', '31/34'], $productDocument->size);
    }
}

final class ProductDocument extends BaseProduct
{
    /** @var list<string> */
    #[MapProductOption(['t_shirt_size', 'dress_size', 'jeans_size'])]
    public array $size = [];
}
