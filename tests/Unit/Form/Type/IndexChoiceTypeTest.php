<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\Form\Type;

use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\Config\IndexRegistry;
use Setono\SyliusMeilisearchPlugin\Document\Product as ProductDocument;
use Setono\SyliusMeilisearchPlugin\Document\Taxon as TaxonDocument;
use Setono\SyliusMeilisearchPlugin\Form\Type\IndexChoiceType;
use Setono\SyliusMeilisearchPlugin\Tests\Application\Entity\Product as ProductEntity;
use Setono\SyliusMeilisearchPlugin\Tests\Application\Entity\Taxon as TaxonEntity;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;

/**
 * @covers \Setono\SyliusMeilisearchPlugin\Form\Type\IndexChoiceType
 */
final class IndexChoiceTypeTest extends TypeTestCase
{
    protected function getExtensions(): array
    {
        $indexRegistry = new IndexRegistry();
        $indexRegistry->add(new Index('products', ProductDocument::class, [ProductEntity::class], new Container()));
        $indexRegistry->add(new Index('taxons', TaxonDocument::class, [TaxonEntity::class], new Container()));
        $indexRegistry->add(new Index('autocomplete', ProductDocument::class, [ProductEntity::class], new Container(), dynamicFields: false));

        return [
            new PreloadedExtension([new IndexChoiceType($indexRegistry)], []),
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
        self::assertSame(['Products', 'Taxons', 'Autocomplete'], $this->labels([]));
    }

    /**
     * @test
     */
    public function it_only_offers_indexes_supporting_dynamic_fields_when_asked_to(): void
    {
        // taxons does not index products and autocomplete opted out via the dynamic_fields flag
        self::assertSame(['Products'], $this->labels(['dynamic_fields_only' => true]));
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return list<string>
     */
    private function labels(array $options): array
    {
        $view = $this->factory->create(IndexChoiceType::class, options: $options)->createView();

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

        return $labels;
    }
}
