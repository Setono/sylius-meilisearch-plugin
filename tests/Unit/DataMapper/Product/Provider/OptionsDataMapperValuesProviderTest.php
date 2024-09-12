<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\DataMapper\Product\Provider;

use PHPUnit\Framework\TestCase;
use Setono\SyliusMeilisearchPlugin\DataMapper\Product\Provider\OptionsDataMapperValuesProvider;
use Setono\SyliusMeilisearchPlugin\Tests\Application\Entity\Product;
use Sylius\Component\Core\Model\ProductVariant;
use Sylius\Component\Product\Model\ProductOption;
use Sylius\Component\Product\Model\ProductOptionValue;

final class OptionsDataMapperValuesProviderTest extends TestCase
{
    public function testItProvidesUniqueOptionsFromProductEnabledVariants(): void
    {
        $provider = new OptionsDataMapperValuesProvider();

        $product = $this->configureProduct();

        self::assertSame(
            [
                'jeans_size' => ['S', 'M'],
                'jeans_color' => ['Black', 'White'],
            ],
            $provider->provide($product)
        );
    }

    private function configureProduct(): Product
    {
        $product = new Product();

        $jeansSizeOption = new ProductOption();
        $jeansSizeOption->setCode('jeans_size');

        $smallSizeOptionValue = new ProductOptionValue();
        $smallSizeOptionValue->setCurrentLocale('en_US');
        $smallSizeOptionValue->setValue('S');
        $jeansSizeOption->addValue($smallSizeOptionValue);

        $mediumSizeOptionValue = new ProductOptionValue();
        $mediumSizeOptionValue->setCurrentLocale('en_US');
        $mediumSizeOptionValue->setValue('M');
        $jeansSizeOption->addValue($mediumSizeOptionValue);

        $jeansColorOption = new ProductOption();
        $jeansColorOption->setCode('jeans_color');

        $blackColorOptionValue = new ProductOptionValue();
        $blackColorOptionValue->setCurrentLocale('en_US');
        $blackColorOptionValue->setValue('Black');
        $jeansColorOption->addValue($blackColorOptionValue);

        $whiteColorOptionValue = new ProductOptionValue();
        $whiteColorOptionValue->setCurrentLocale('en_US');
        $whiteColorOptionValue->setValue('White');
        $jeansColorOption->addValue($whiteColorOptionValue);

        $firstEnabledVariant = new ProductVariant();
        $firstEnabledVariant->enable();
        $firstEnabledVariant->addOptionValue($smallSizeOptionValue);
        $firstEnabledVariant->addOptionValue($blackColorOptionValue);

        $disabledVariant = new ProductVariant();
        $disabledVariant->disable();

        $secondEnabledVariant = new ProductVariant();
        $secondEnabledVariant->enable();
        $secondEnabledVariant->addOptionValue($mediumSizeOptionValue);
        $secondEnabledVariant->addOptionValue($whiteColorOptionValue);

        $product->addVariant($firstEnabledVariant);
        $product->addVariant($disabledVariant);
        $product->addVariant($secondEnabledVariant);

        return $product;
    }
}
