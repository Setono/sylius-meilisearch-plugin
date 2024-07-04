<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Resolver\IndexName;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\Document\Product;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScope;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScopeProviderInterface;
use Setono\SyliusMeilisearchPlugin\Resolver\IndexName\IndexNameResolver;
use Symfony\Component\DependencyInjection\Container;

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
        $index = new Index('products', Product::class, [], new Container(), 'prefix');
        $indexScope = new IndexScope($index, 'FASHION_WEB', 'en_US', 'USD');
        $resolver = new IndexNameResolver(
            $this->prophesize(IndexScopeProviderInterface::class)->reveal(),
            'prod',
        );

        self::assertSame('prefix__prod__products__fashion_web__en_us__usd', $resolver->resolveFromIndexScope($indexScope));
    }
}
