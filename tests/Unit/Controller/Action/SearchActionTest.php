<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\Controller\Action;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusMeilisearchPlugin\Controller\Action\SearchAction;
use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
use Sylius\Component\Resource\Model\ToggleableInterface;

/**
 * @covers \Setono\SyliusMeilisearchPlugin\Controller\Action\SearchAction
 */
final class SearchActionTest extends TestCase
{
    use ProphecyTrait;

    private function indexable(): IndexableInterface
    {
        return $this->prophesize(IndexableInterface::class)->reveal();
    }

    private function enabledToggleable(bool $enabled): IndexableInterface
    {
        $entity = $this->prophesize(IndexableInterface::class);
        $entity->willImplement(ToggleableInterface::class);
        $entity->isEnabled()->willReturn($enabled);

        return $entity->reveal();
    }

    /**
     * A Meilisearch hit carries its own primary key ('id', the document identifier) alongside the
     * entity coordinates.
     *
     * @param class-string<IndexableInterface> $class
     *
     * @return array{id: string, entityClass: class-string<IndexableInterface>, entityId: int|string}
     */
    private function hit(string $class, int|string $id): array
    {
        return ['id' => (string) $id, 'entityClass' => $class, 'entityId' => $id];
    }

    /**
     * @param class-string<IndexableInterface> $class
     *
     * @return array{entityClass: class-string<IndexableInterface>, entityId: int|string, documentIdentifier: string|null}
     */
    private function stale(string $class, int|string $id): array
    {
        return ['entityClass' => $class, 'entityId' => $id, 'documentIdentifier' => (string) $id];
    }

    /**
     * @test
     */
    public function it_reorders_loaded_entities_to_match_the_hit_order(): void
    {
        $class = IndexableInterface::class;
        $a = $this->indexable();
        $b = $this->indexable();
        $c = $this->indexable();

        // Loaded in a different order than the hits (as findBy would return them)
        $loaded = [$class => ['2' => $b, '3' => $c, '1' => $a]];
        $hits = [$this->hit($class, 1), $this->hit($class, 2), $this->hit($class, 3)];

        [$items, $stale] = SearchAction::reorderAndFilter($hits, $loaded);

        self::assertSame([$a, $b, $c], $items);
        self::assertSame([], $stale);
    }

    /**
     * @test
     */
    public function it_drops_and_reports_a_hit_whose_entity_is_missing(): void
    {
        $class = IndexableInterface::class;
        $a = $this->indexable();

        $loaded = [$class => ['1' => $a]];
        $hits = [$this->hit($class, 1), $this->hit($class, 2)];

        [$items, $stale] = SearchAction::reorderAndFilter($hits, $loaded);

        self::assertSame([$a], $items);
        self::assertSame([$this->stale($class, 2)], $stale);
    }

    /**
     * @test
     */
    public function it_drops_and_reports_a_disabled_entity(): void
    {
        $class = IndexableInterface::class;
        $enabled = $this->enabledToggleable(true);
        $disabled = $this->enabledToggleable(false);

        $loaded = [$class => ['1' => $enabled, '2' => $disabled]];
        $hits = [$this->hit($class, 1), $this->hit($class, 2)];

        [$items, $stale] = SearchAction::reorderAndFilter($hits, $loaded);

        self::assertSame([$enabled], $items);
        self::assertSame([$this->stale($class, 2)], $stale);
    }
}
