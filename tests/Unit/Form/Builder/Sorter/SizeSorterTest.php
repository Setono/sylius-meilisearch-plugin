<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\Form\Builder\Sorter;

use PHPUnit\Framework\TestCase;
use Setono\SyliusMeilisearchPlugin\Form\Builder\Sorter\SizeSorter;

final class SizeSorterTest extends TestCase
{
    public function testItSortsChoices(): void
    {
        $choices = ['XL' => 'XL', 'S' => 'S', 'M' => 'M', 'L' => 'L', 'XXL' => 'XXL'];

        self::assertSame(
            ['S' => 'S', 'M' => 'M', 'L' => 'L', 'XL' => 'XL', 'XXL' => 'XXL'],
            (new SizeSorter())->sort($choices),
        );
    }

    public function testItSortsChoiceWithAdditionalElements(): void
    {
        $choices = ['XL' => 'XL', 'S' => 'S', 'M' => 'M', 'XXXL' => 'XXXL', 'L' => 'L', 'XXL' => 'XXL'];

        self::assertSame(
            ['S' => 'S', 'M' => 'M', 'L' => 'L', 'XL' => 'XL', 'XXL' => 'XXL', 'XXXL' => 'XXXL'],
            (new SizeSorter())->sort($choices),
        );
    }

    public function testItSortsChoiceOmittingNonExistentTemplateElements(): void
    {
        $choices = ['M' => 'M', 'S' => 'S', 'L' => 'L'];

        self::assertSame(
            ['S' => 'S', 'M' => 'M', 'L' => 'L'],
            (new SizeSorter())->sort($choices),
        );
    }
}
