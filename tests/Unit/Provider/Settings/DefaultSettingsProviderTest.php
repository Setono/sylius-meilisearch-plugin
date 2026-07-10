<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\Provider\Settings;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\Metadata;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\MetadataFactoryInterface;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\Searchable;
use Setono\SyliusMeilisearchPlugin\Document\Product as ProductDocument;
use Setono\SyliusMeilisearchPlugin\Meilisearch\SynonymResolverInterface;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScope;
use Setono\SyliusMeilisearchPlugin\Provider\Settings\DefaultSettingsProvider;
use Setono\SyliusMeilisearchPlugin\Tests\Application\Entity\Product;
use Symfony\Component\DependencyInjection\Container;

/**
 * @covers \Setono\SyliusMeilisearchPlugin\Provider\Settings\DefaultSettingsProvider
 */
final class DefaultSettingsProviderTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_emits_an_ordered_searchable_attributes_list(): void
    {
        $metadata = new Metadata(ProductDocument::class);
        // deliberately added in a non-priority order to prove the provider orders them
        $metadata->searchableAttributes['description'] = new Searchable('description', 10);
        $metadata->searchableAttributes['name'] = new Searchable('name', 100);
        $metadata->searchableAttributes['brand'] = new Searchable('brand', 50);

        $metadataFactory = $this->prophesize(MetadataFactoryInterface::class);
        $metadataFactory->getMetadataFor(ProductDocument::class)->willReturn($metadata);

        $synonymResolver = $this->prophesize(SynonymResolverInterface::class);
        $synonymResolver->resolve(Argument::type(IndexScope::class))->willReturn([]);

        $provider = new DefaultSettingsProvider($synonymResolver->reveal(), $metadataFactory->reveal());

        $index = new Index('products', ProductDocument::class, [Product::class], new Container());
        $settings = $provider->getSettings(new IndexScope($index));

        self::assertSame(['name', 'brand', 'description'], $settings->searchableAttributes->jsonSerialize());
    }

    /**
     * @test
     */
    public function it_falls_back_to_the_wildcard_when_no_attribute_is_searchable(): void
    {
        $metadata = new Metadata(ProductDocument::class);

        $metadataFactory = $this->prophesize(MetadataFactoryInterface::class);
        $metadataFactory->getMetadataFor(ProductDocument::class)->willReturn($metadata);

        $synonymResolver = $this->prophesize(SynonymResolverInterface::class);
        $synonymResolver->resolve(Argument::type(IndexScope::class))->willReturn([]);

        $provider = new DefaultSettingsProvider($synonymResolver->reveal(), $metadataFactory->reveal());

        $index = new Index('products', ProductDocument::class, [Product::class], new Container());
        $settings = $provider->getSettings(new IndexScope($index));

        self::assertSame(['*'], $settings->searchableAttributes->jsonSerialize());
    }
}
