<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\Meilisearch\Autocomplete;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\Document\Product;
use Setono\SyliusMeilisearchPlugin\Meilisearch\Autocomplete\DefaultSourceResolver;
use Setono\SyliusMeilisearchPlugin\Resolver\IndexUid\IndexUidResolverInterface;
use Symfony\Component\DependencyInjection\Container;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * @covers \Setono\SyliusMeilisearchPlugin\Meilisearch\Autocomplete\DefaultSourceResolver
 */
final class DefaultSourceResolverTest extends TestCase
{
    use ProphecyTrait;

    private const SHARED_TEMPLATE = '@SetonoSyliusMeilisearchPlugin/autocomplete/templates/item.html.twig';

    private const PRODUCTS_TEMPLATE = '@SetonoSyliusMeilisearchPlugin/autocomplete/templates/products/item.html.twig';

    /**
     * @test
     */
    public function it_resolves_a_source_with_the_shared_template_and_defaults(): void
    {
        $index = new Index('products', Product::class, [], new Container());

        $indexUidResolver = $this->prophesize(IndexUidResolverInterface::class);
        $indexUidResolver->resolve($index)->willReturn('prod__products');

        $twig = new Environment(new ArrayLoader([
            self::SHARED_TEMPLATE => 'SHARED',
        ]));

        $resolver = new DefaultSourceResolver($indexUidResolver->reveal(), $twig, 5);
        $source = $resolver->resolve($index);

        self::assertSame('products', $source->id);
        self::assertSame('prod__products', $source->index);
        self::assertSame('url', $source->urlAttribute);
        self::assertSame(5, $source->limit);
        self::assertSame(['item' => 'SHARED'], $source->templates);
    }

    /**
     * @test
     */
    public function it_prefers_a_per_index_template_when_it_exists(): void
    {
        $index = new Index('products', Product::class, [], new Container());

        $indexUidResolver = $this->prophesize(IndexUidResolverInterface::class);
        $indexUidResolver->resolve($index)->willReturn('prod__products');

        $twig = new Environment(new ArrayLoader([
            self::SHARED_TEMPLATE => 'SHARED',
            self::PRODUCTS_TEMPLATE => 'PRODUCTS',
        ]));

        $resolver = new DefaultSourceResolver($indexUidResolver->reveal(), $twig, 5);
        $source = $resolver->resolve($index);

        self::assertSame(['item' => 'PRODUCTS'], $source->templates);
    }

    /**
     * @test
     */
    public function it_uses_the_configured_url_attribute(): void
    {
        $index = new Index('products', Product::class, [], new Container());

        $indexUidResolver = $this->prophesize(IndexUidResolverInterface::class);
        $indexUidResolver->resolve($index)->willReturn('prod__products');

        $twig = new Environment(new ArrayLoader([
            self::SHARED_TEMPLATE => 'SHARED',
        ]));

        $resolver = new DefaultSourceResolver($indexUidResolver->reveal(), $twig, 8, 'slug');
        $source = $resolver->resolve($index);

        self::assertSame('slug', $source->urlAttribute);
        self::assertSame(8, $source->limit);
    }
}
