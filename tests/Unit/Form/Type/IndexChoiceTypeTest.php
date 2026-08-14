<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\Form\Type;

use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusMeilisearchPlugin\Config\IndexRegistryInterface;
use Setono\SyliusMeilisearchPlugin\Form\Type\IndexChoiceType;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;

/**
 * @covers \Setono\SyliusMeilisearchPlugin\Form\Type\IndexChoiceType
 */
final class IndexChoiceTypeTest extends TypeTestCase
{
    use ProphecyTrait;

    protected function getExtensions(): array
    {
        $indexRegistry = $this->prophesize(IndexRegistryInterface::class);
        $indexRegistry->getNames()->willReturn(['products', 'taxons']);

        return [
            new PreloadedExtension([new IndexChoiceType($indexRegistry->reveal())], []),
        ];
    }

    /**
     * The choice_label callable is only invoked (with three arguments) when the choice list
     * view is built, so createView() is required to cover the admin rendering path.
     *
     * @test
     */
    public function it_builds_a_form_view_with_capitalized_labels(): void
    {
        $view = $this->factory->create(IndexChoiceType::class)->createView();

        $vars = $view->vars;
        self::assertIsArray($vars);
        self::assertArrayHasKey('choices', $vars);
        self::assertIsArray($vars['choices']);

        $labels = [];
        foreach ($vars['choices'] as $choice) {
            self::assertInstanceOf(ChoiceView::class, $choice);
            self::assertIsString($choice->label);
            $labels[] = $choice->label;
        }

        self::assertSame(['Products', 'Taxons'], $labels);
    }
}
