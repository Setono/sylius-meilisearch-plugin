<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\DataMapper\Product\Provider;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusMeilisearchPlugin\DataMapper\Product\Provider\AttributesDataMapperValuesProvider;
use Setono\SyliusMeilisearchPlugin\Tests\Application\Entity\Product;
use Sylius\Component\Product\Model\ProductAttribute;
use Sylius\Component\Product\Model\ProductAttributeValue;

final class AttributesDataMapperValuesProviderTest extends TestCase
{
    use ProphecyTrait;

    public function testItProvidesUniqueOptionsFromProductEnabledVariants(): void
    {
        $provider = new AttributesDataMapperValuesProvider();

        $product = $this->configureProduct();

        self::assertSame(
            [
                'brand' => 'Best brand',
                'collection' => 'Best collection',
            ],
            $provider->provide($product, ['locale_code' => 'en_US'])
        );
    }

    private function configureProduct(): Product
    {
        $product = new Product();

        $brandAttribute = new ProductAttribute();
        $brandAttribute->setCode('brand');
        $brandAttribute->setStorageType('text');
        $brandAttributeValue = new ProductAttributeValue();
        $brandAttributeValue->setAttribute($brandAttribute);
        $brandAttributeValue->setValue('Best brand');

        $collectionAttribute = new ProductAttribute();
        $collectionAttribute->setCode('collection');
        $collectionAttribute->setStorageType('text');
        $collectionAttributeValue = new ProductAttributeValue();
        $collectionAttributeValue->setAttribute($collectionAttribute);
        $collectionAttributeValue->setValue('Best collection');

        $product->addAttribute($brandAttributeValue);
        $product->addAttribute($collectionAttributeValue);

        return $product;
    }
}
