<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\Settings;

use PHPUnit\Framework\TestCase;
use Setono\SyliusMeilisearchPlugin\Settings\UniqueList;

final class UniqueListTest extends TestCase
{
    public function test_add(): void
    {
        $list = new UniqueList();
        $list->add('foo', 'bar', 'baz');

        self::assertCount(3, $list);
        self::assertSame('foo', $list[0]);
        self::assertSame('bar', $list[1]);
        self::assertSame('baz', $list[2]);
    }

    public function test_add_duplicates(): void
    {
        $list = new UniqueList();
        $list->add('foo', 'bar', 'baz');
        $list->add('foo', 'bar', 'baz');

        self::assertCount(3, $list);
        self::assertSame('foo', $list[0]);
        self::assertSame('bar', $list[1]);
        self::assertSame('baz', $list[2]);
    }

    public function test_offsetExists(): void
    {
        $list = new UniqueList();
        $list->add('foo', 'bar', 'baz');

        self::assertTrue(isset($list[0]));
        self::assertTrue(isset($list[1]));
        self::assertTrue(isset($list[2]));
        self::assertFalse(isset($list[3]));
    }

    public function test_offsetGet(): void
    {
        $list = new UniqueList();
        $list->add('foo', 'bar', 'baz');

        self::assertSame('foo', $list[0]);
        self::assertSame('bar', $list[1]);
        self::assertSame('baz', $list[2]);
    }

    public function test_offsetSet(): void
    {
        $list = new UniqueList();
        $list[] = 'foo';
        $list[] = 'bar';
        $list[] = 'baz';

        self::assertCount(3, $list);
        self::assertSame('foo', $list[0]);
        self::assertSame('bar', $list[1]);
        self::assertSame('baz', $list[2]);
    }

    public function test_offsetSet_with_offset(): void
    {
        $this->expectException(\LogicException::class);

        $list = new UniqueList();
        $list[0] = 'foo';
    }

    public function test_offsetUnset(): void
    {
        $list = new UniqueList();
        $list->add('foo', 'bar', 'baz');
        unset($list[1]);

        self::assertCount(2, $list);
        self::assertSame('foo', $list[0]);
        self::assertSame('baz', $list[1]);
    }

    /**
     * @test
     */
    public function it_returns_empty_data_if_empty(): void
    {
        $list = new UniqueList(ifEmpty: ['foo', 'bar', 'baz']);
        self::assertSame(['foo', 'bar', 'baz'], $list->jsonSerialize());
    }
}
