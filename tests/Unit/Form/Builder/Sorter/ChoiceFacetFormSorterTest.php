<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\Form\Builder\Sorter;

use PHPUnit\Framework\TestCase;
use Setono\SyliusMeilisearchPlugin\Form\Builder\Sorter\ChoiceFacetFormSorter;

final class ChoiceFacetFormSorterTest extends TestCase
{
    public function testItSortsChoices(): void
    {
        $choices = ['XL' => 'XL', 'S' => 'S', 'M' => 'M', 'L' => 'L', 'XXL' => 'XXL'];
        $template = ['S', 'M', 'L', 'XL', 'XXL'];

        self::assertSame(
            ['S' => 'S', 'M' => 'M', 'L' => 'L', 'XL' => 'XL', 'XXL' => 'XXL'],
            ChoiceFacetFormSorter::sort($choices, $template)
        );
    }

    public function testItSortsChoiceWithAdditionalElements(): void
    {
        $choices = ['XL' => 'XL', 'S' => 'S', 'M' => 'M', 'XXXL' => 'XXXL', 'L' => 'L', 'XXL' => 'XXL'];
        $template = ['S', 'M', 'L', 'XL', 'XXL'];

        self::assertSame(
            ['S' => 'S', 'M' => 'M', 'L' => 'L', 'XL' => 'XL', 'XXL' => 'XXL', 'XXXL' => 'XXXL'],
            ChoiceFacetFormSorter::sort($choices, $template)
        );
    }

    public function testItSortsChoiceOmittingNonExistentTemplateElements(): void
    {
        $choices = ['M' => 'M', 'S' => 'S', 'L' => 'L'];
        $template = ['XS', 'S', 'M', 'L', 'XL', 'XXL'];

        self::assertSame(
            ['S' => 'S', 'M' => 'M', 'L' => 'L'],
            ChoiceFacetFormSorter::sort($choices, $template)
        );
    }
}
