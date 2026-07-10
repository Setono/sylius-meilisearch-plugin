<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\Meilisearch\Query;

use Meilisearch\Contracts\SearchQuery;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\Document\Attribute\Sortable as SortableAttribute;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\Metadata;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\MetadataFactoryInterface;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\Sortable;
use Setono\SyliusMeilisearchPlugin\Document\Product as ProductDocument;
use Setono\SyliusMeilisearchPlugin\Engine\SearchRequest;
use Setono\SyliusMeilisearchPlugin\Meilisearch\Filter\FilterBuilderInterface;
use Setono\SyliusMeilisearchPlugin\Meilisearch\Query\MultiSearchBuilder;
use Setono\SyliusMeilisearchPlugin\Meilisearch\Query\SearchQueryBuilderInterface;
use Setono\SyliusMeilisearchPlugin\Resolver\IndexUid\IndexUidResolverInterface;
use Setono\SyliusMeilisearchPlugin\Tests\Application\Entity\Product;
use Symfony\Component\DependencyInjection\Container;

/**
 * @covers \Setono\SyliusMeilisearchPlugin\Meilisearch\Query\MultiSearchBuilder
 */
final class MultiSearchBuilderTest extends TestCase
{
    use ProphecyTrait;

    private function createBuilder(): MultiSearchBuilder
    {
        $searchQueryBuilder = $this->prophesize(SearchQueryBuilderInterface::class);
        $searchQueryBuilder->build(Argument::cetera())->will(static fn (): SearchQuery => new SearchQuery());

        $filterBuilder = $this->prophesize(FilterBuilderInterface::class);
        $filterBuilder->build(Argument::cetera())->willReturn([]);

        return new MultiSearchBuilder($searchQueryBuilder->reveal(), $filterBuilder->reveal(), 60);
    }

    private function createIndex(): Index
    {
        $metadata = new Metadata(ProductDocument::class);
        $metadata->sortableAttributes['createdAt'] = new Sortable('createdAt');
        $metadata->sortableAttributes['price'] = new Sortable('price', SortableAttribute::ASC);

        $metadataFactory = $this->prophesize(MetadataFactoryInterface::class);
        $metadataFactory->getMetadataFor(ProductDocument::class)->willReturn($metadata);

        $uidResolver = $this->prophesize(IndexUidResolverInterface::class);
        $uidResolver->resolve(Argument::type(Index::class))->willReturn('products__test');

        $locator = new Container();
        $locator->set(MetadataFactoryInterface::class, $metadataFactory->reveal());
        $locator->set(IndexUidResolverInterface::class, $uidResolver->reveal());

        return new Index('products', ProductDocument::class, [Product::class], $locator);
    }

    private function buildSort(?string $sort): ?array
    {
        $queries = $this->createBuilder()->build(
            $this->createIndex(),
            new SearchRequest('query', [], 1, $sort),
        );

        return $queries[0]->toArray()['sort'] ?? null;
    }

    /**
     * @test
     */
    public function it_applies_a_valid_sort(): void
    {
        self::assertSame(['createdAt:asc'], $this->buildSort('createdAt:asc'));
        self::assertSame(['createdAt:desc'], $this->buildSort('createdAt:desc'));
        self::assertSame(['price:asc'], $this->buildSort('price:asc'));
    }

    /**
     * @test
     */
    public function it_falls_back_to_relevance_for_an_unknown_attribute(): void
    {
        self::assertNull($this->buildSort('unknown:asc'));
    }

    /**
     * @test
     */
    public function it_falls_back_to_relevance_for_a_disallowed_direction(): void
    {
        // price is restricted to asc via #[Sortable(direction: 'asc')]
        self::assertNull($this->buildSort('price:desc'));
    }

    /**
     * @test
     */
    public function it_falls_back_to_relevance_for_garbage_input(): void
    {
        self::assertNull($this->buildSort('garbage'));
        self::assertNull($this->buildSort('createdAt'));
        self::assertNull($this->buildSort('createdAt:asc:desc'));
        self::assertNull($this->buildSort(null));
    }
}
