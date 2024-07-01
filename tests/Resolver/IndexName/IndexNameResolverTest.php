<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Resolver\IndexName;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\Config\IndexRegistry;
use Setono\SyliusMeilisearchPlugin\Document\Product;
use Setono\SyliusMeilisearchPlugin\Indexer\IndexerInterface;
use Setono\SyliusMeilisearchPlugin\IndexScope\IndexScope;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScopeProviderInterface;
use Setono\SyliusMeilisearchPlugin\Resolver\IndexName\IndexNameResolver;

/**
 * @covers \Setono\SyliusMeilisearchPlugin\Resolver\IndexName\IndexNameResolver
 */
final class IndexNameResolverTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_resolves_from_index_scope(): void
    {
        $index = new Index(
            'products',
            Product::class,
            $this->prophesize(IndexerInterface::class)->reveal(),
            [],
            'prefix'
        );

        $resolver = new IndexNameResolver(
            new IndexRegistry(),
            $this->prophesize(IndexScopeProviderInterface::class)->reveal(),
            'prod'
        );

        $indexScope = (new IndexScope($index))
            ->withChannelCode('FASHION_WEB')
            ->withLocaleCode('en_US')
            ->withCurrencyCode('USD')
        ;

        self::assertSame('prefix__prod__products__fashion_web__en_us__usd', $resolver->resolveFromIndexScope($indexScope));
    }
}
