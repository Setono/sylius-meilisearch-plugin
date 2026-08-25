<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\Provider\IndexUids;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\Config\IndexRegistry;
use Setono\SyliusMeilisearchPlugin\Document\Product as ProductDocument;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScope;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScopeProviderInterface;
use Setono\SyliusMeilisearchPlugin\Provider\IndexUids\IndexUidsProvider;
use Setono\SyliusMeilisearchPlugin\Resolver\IndexUid\IndexUidResolverInterface;
use Symfony\Component\DependencyInjection\Container;

/**
 * @covers \Setono\SyliusMeilisearchPlugin\Provider\IndexUids\IndexUidsProvider
 */
final class IndexUidsProviderTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_provides_uids_keyed_by_index_name(): void
    {
        $products = new Index('products', ProductDocument::class, [], new Container());
        $taxons = new Index('taxons', ProductDocument::class, [], new Container());

        $productsScope1 = new IndexScope($products, 'FASHION_WEB', 'en_US', 'USD');
        $productsScope2 = new IndexScope($products, 'FASHION_WEB', 'da_DK', 'DKK');
        $taxonsScope = new IndexScope($taxons, 'FASHION_WEB', 'en_US');

        $indexRegistry = new IndexRegistry();
        $indexRegistry->add($products);
        $indexRegistry->add($taxons);

        $indexScopeProvider = $this->prophesize(IndexScopeProviderInterface::class);
        $indexScopeProvider->getAll($products)->willReturn([$productsScope1, $productsScope2]);
        $indexScopeProvider->getAll($taxons)->willReturn([$taxonsScope]);

        $indexUidResolver = $this->prophesize(IndexUidResolverInterface::class);
        $indexUidResolver->resolveFromIndexScope($productsScope1)->willReturn('products__fashion_web__en_us__usd');
        $indexUidResolver->resolveFromIndexScope($productsScope2)->willReturn('products__fashion_web__da_dk__dkk');
        $indexUidResolver->resolveFromIndexScope($taxonsScope)->willReturn('taxons__fashion_web__en_us');

        $provider = new IndexUidsProvider($indexRegistry, $indexScopeProvider->reveal(), $indexUidResolver->reveal());

        self::assertSame([
            'products' => ['products__fashion_web__en_us__usd', 'products__fashion_web__da_dk__dkk'],
            'taxons' => ['taxons__fashion_web__en_us'],
        ], $provider->getAll());

        // a single index only resolves that index's scopes
        self::assertSame(['taxons__fashion_web__en_us'], $provider->get('taxons'));
    }

    /**
     * @test
     */
    public function it_throws_when_getting_uids_for_an_unknown_index(): void
    {
        $provider = new IndexUidsProvider(
            new IndexRegistry(),
            $this->prophesize(IndexScopeProviderInterface::class)->reveal(),
            $this->prophesize(IndexUidResolverInterface::class)->reveal(),
        );

        $this->expectException(\InvalidArgumentException::class);

        $provider->get('unknown');
    }

    /**
     * @test
     */
    public function it_deduplicates_uids(): void
    {
        $products = new Index('products', ProductDocument::class, [], new Container());

        $scope1 = new IndexScope($products, 'FASHION_WEB', 'en_US', 'USD');
        $scope2 = new IndexScope($products, 'OTHER_CHANNEL', 'en_US', 'USD');

        $indexRegistry = new IndexRegistry();
        $indexRegistry->add($products);

        $indexScopeProvider = $this->prophesize(IndexScopeProviderInterface::class);
        $indexScopeProvider->getAll($products)->willReturn([$scope1, $scope2]);

        // Both scopes resolve to the same uid, e.g. when the uid does not include the channel
        $indexUidResolver = $this->prophesize(IndexUidResolverInterface::class);
        $indexUidResolver->resolveFromIndexScope($scope1)->willReturn('products__en_us__usd');
        $indexUidResolver->resolveFromIndexScope($scope2)->willReturn('products__en_us__usd');

        $provider = new IndexUidsProvider($indexRegistry, $indexScopeProvider->reveal(), $indexUidResolver->reveal());

        self::assertSame(['products' => ['products__en_us__usd']], $provider->getAll());
    }
}
