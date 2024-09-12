<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\DataMapper\Product\Provider;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusMeilisearchPlugin\DataMapper\Product\Provider\ReflectionAttributeValuesProvider;
use Setono\SyliusMeilisearchPlugin\Document\Attribute\MapProductAttribute;
use Setono\SyliusMeilisearchPlugin\Document\Attribute\MapProductOption;
use Setono\SyliusMeilisearchPlugin\Tests\Application\Document\Product;

final class ReflectionAttributeValuesProviderTest extends TestCase
{
    use ProphecyTrait;

    public function testItProvidesReflectionAttributeValues(): void
    {
        $provider = new ReflectionAttributeValuesProvider(MapProductAttribute::class);

        $reflectionAttribute = $this->prophesize(\ReflectionAttribute::class);
        $reflectionAttribute->newInstance()->willReturn(new MapProductAttribute(['dress_brand']));

        self::assertSame(
            ['Celsius Small'],
            $provider->provide(
                $reflectionAttribute->reveal(),
                new Product(),
                'brand',
                ['dress_brand' => 'Celsius Small', 'collection' => 'Spring/Summer'],
            )
        );
    }

    public function testItThrowsExceptionIfAttributeHasWrongType(): void
    {
        $provider = new ReflectionAttributeValuesProvider(MapProductOption::class);

        $reflectionAttribute = $this->prophesize(\ReflectionAttribute::class);
        $reflectionAttribute->newInstance()->willReturn(new MapProductAttribute(['dress_brand']));

        $this->expectException(\InvalidArgumentException::class);

        $provider->provide(
            $reflectionAttribute->reveal(),
            new Product(),
            'brand',
            ['dress_brand' => 'Celsius Small', 'collection' => 'Spring/Summer'],
        );
    }

    public function testItThrowsExceptionIfTargetHasNoDefinedProperty(): void
    {
        $provider = new ReflectionAttributeValuesProvider(MapProductAttribute::class);

        $reflectionAttribute = $this->prophesize(\ReflectionAttribute::class);
        $reflectionAttribute->newInstance()->willReturn(new MapProductAttribute(['dress_brand']));

        $this->expectException(\InvalidArgumentException::class);

        $provider->provide(
            $reflectionAttribute->reveal(),
            new Product(),
            'badPropertyName',
            ['dress_brand' => 'Celsius Small', 'collection' => 'Spring/Summer'],
        );
    }

    public function testItThrowsExceptionIfDefinedPropertyIsNotArray(): void
    {
        $provider = new ReflectionAttributeValuesProvider(MapProductAttribute::class);

        $reflectionAttribute = $this->prophesize(\ReflectionAttribute::class);
        $reflectionAttribute->newInstance()->willReturn(new MapProductAttribute(['dress_brand']));

        $this->expectException(\InvalidArgumentException::class);

        $provider->provide(
            $reflectionAttribute->reveal(),
            new Product(),
            'url',
            ['dress_brand' => 'Celsius Small', 'collection' => 'Spring/Summer'],
        );
    }
}
