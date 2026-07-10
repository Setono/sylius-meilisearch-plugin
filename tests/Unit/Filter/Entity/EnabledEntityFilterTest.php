<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\Filter\Entity;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\Document\Product as ProductDocument;
use Setono\SyliusMeilisearchPlugin\Filter\Entity\EnabledEntityFilter;
use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScope;
use Setono\SyliusMeilisearchPlugin\Tests\Application\Entity\Product;
use Sylius\Component\Resource\Model\ToggleableInterface;
use Symfony\Component\DependencyInjection\Container;

/**
 * @covers \Setono\SyliusMeilisearchPlugin\Filter\Entity\EnabledEntityFilter
 */
final class EnabledEntityFilterTest extends TestCase
{
    use ProphecyTrait;

    private function indexScope(string $indexName): IndexScope
    {
        return new IndexScope(new Index($indexName, ProductDocument::class, [Product::class], new Container()));
    }

    private function toggleable(bool $enabled): IndexableInterface
    {
        $entity = $this->prophesize(IndexableInterface::class);
        $entity->willImplement(ToggleableInterface::class);
        $entity->isEnabled()->willReturn($enabled);

        return $entity->reveal();
    }

    /**
     * @test
     */
    public function it_keeps_enabled_entities(): void
    {
        $filter = new EnabledEntityFilter('products');

        self::assertTrue($filter->filter($this->toggleable(true), new ProductDocument(), $this->indexScope('products')));
    }

    /**
     * @test
     */
    public function it_filters_out_disabled_entities(): void
    {
        $filter = new EnabledEntityFilter('products');

        self::assertFalse($filter->filter($this->toggleable(false), new ProductDocument(), $this->indexScope('products')));
    }

    /**
     * @test
     */
    public function it_ignores_scopes_for_other_indexes(): void
    {
        $filter = new EnabledEntityFilter('products');

        // Disabled, but the scope is for another index, so this filter does not apply
        self::assertTrue($filter->filter($this->toggleable(false), new ProductDocument(), $this->indexScope('taxons')));
    }

    /**
     * @test
     */
    public function it_keeps_non_toggleable_entities(): void
    {
        $filter = new EnabledEntityFilter('products');

        self::assertTrue($filter->filter(
            $this->prophesize(IndexableInterface::class)->reveal(),
            new ProductDocument(),
            $this->indexScope('products'),
        ));
    }
}
