<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\Document\Attribute;

use PHPUnit\Framework\TestCase;
use Setono\SyliusMeilisearchPlugin\Document\Attribute\Sortable;

/**
 * @covers \Setono\SyliusMeilisearchPlugin\Document\Attribute\Sortable
 */
final class SortableTest extends TestCase
{
    /**
     * @test
     */
    public function it_accepts_the_allowed_directions_and_null(): void
    {
        self::assertSame('asc', (new Sortable(Sortable::ASC))->direction);
        self::assertSame('desc', (new Sortable(Sortable::DESC))->direction);
        self::assertNull((new Sortable())->direction);
    }

    /**
     * @test
     */
    public function it_rejects_an_invalid_direction(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/direction must be one of/');

        new Sortable('ascending');
    }
}
