<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\Form\Builder;

use PHPUnit\Framework\TestCase;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\Facet;
use Setono\SyliusMeilisearchPlugin\Form\Builder\FacetLabelTrait;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Contracts\Translation\TranslatorInterface;

final class FacetLabelUser
{
    use FacetLabelTrait;

    public function label(TranslatorInterface $translator, Facet $facet): string
    {
        return $this->facetLabel($translator, $facet);
    }
}

/**
 * @covers \Setono\SyliusMeilisearchPlugin\Form\Builder\FacetLabelTrait
 */
final class FacetLabelTraitTest extends TestCase
{
    private function translator(): Translator
    {
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', [
            'setono_sylius_meilisearch.form.search.facet.color' => 'Color',
        ], 'en');

        return $translator;
    }

    /**
     * @test
     */
    public function it_uses_the_translation_key_when_the_catalogue_defines_it(): void
    {
        $user = new FacetLabelUser();

        self::assertSame(
            'setono_sylius_meilisearch.form.search.facet.color',
            $user->label($this->translator(), new Facet('color', 'array')),
        );
    }

    /**
     * @test
     */
    public function it_humanizes_the_facet_name_when_no_translation_exists(): void
    {
        $user = new FacetLabelUser();

        self::assertSame('Brand', $user->label($this->translator(), new Facet('brand', 'array')));
    }
}
