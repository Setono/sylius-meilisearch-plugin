<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\Form\Builder;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\Facet;
use Setono\SyliusMeilisearchPlugin\Form\Builder\FacetLabelResolver;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Component\Product\Model\ProductAttributeInterface;
use Sylius\Component\Product\Model\ProductOptionInterface;
use Sylius\Component\Product\Model\ProductOptionTranslationInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;

/**
 * @covers \Setono\SyliusMeilisearchPlugin\Form\Builder\FacetLabelResolver
 */
final class FacetLabelResolverTest extends TestCase
{
    use ProphecyTrait;

    private function resolver(
        ?ProductAttributeInterface $attribute = null,
        ?ProductOptionInterface $option = null,
    ): FacetLabelResolver {
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', [
            'setono_sylius_meilisearch.form.search.facet.attr_color' => 'Colour',
            'setono_sylius_meilisearch.form.search.facet.color' => 'Color',
        ], 'en');

        $attributeRepository = $this->prophesize(RepositoryInterface::class);
        $attributeRepository->findOneBy(Argument::type('array'))->willReturn($attribute);

        $optionRepository = $this->prophesize(RepositoryInterface::class);
        $optionRepository->findOneBy(Argument::type('array'))->willReturn($option);

        $localeContext = $this->prophesize(LocaleContextInterface::class);
        $localeContext->getLocaleCode()->willReturn('en_US');

        return new FacetLabelResolver(
            $translator,
            $attributeRepository->reveal(),
            $optionRepository->reveal(),
            $localeContext->reveal(),
        );
    }

    /**
     * @test
     */
    public function it_uses_the_translation_key_when_the_catalogue_defines_it(): void
    {
        self::assertSame(
            'setono_sylius_meilisearch.form.search.facet.color',
            $this->resolver()->resolve(new Facet('color', 'array')),
        );
    }

    /**
     * @test
     */
    public function it_prefers_the_translation_key_over_the_attribute_name(): void
    {
        $attribute = $this->prophesize(ProductAttributeInterface::class);
        $attribute->getNameByLocaleCode(Argument::type('string'))->willReturn('Color');

        self::assertSame(
            'setono_sylius_meilisearch.form.search.facet.attr_color',
            $this->resolver(attribute: $attribute->reveal())->resolve(new Facet('attr_color', 'array')),
        );
    }

    /**
     * @test
     */
    public function it_resolves_the_translated_attribute_name(): void
    {
        $attribute = $this->prophesize(ProductAttributeInterface::class);
        $attribute->getNameByLocaleCode('en_US')->willReturn('Material');

        self::assertSame(
            'Material',
            $this->resolver(attribute: $attribute->reveal())->resolve(new Facet('attr_material', 'array')),
        );
    }

    /**
     * @test
     */
    public function it_resolves_the_translated_option_name(): void
    {
        $translation = $this->prophesize(ProductOptionTranslationInterface::class);
        $translation->getName()->willReturn('Size');

        $option = $this->prophesize(ProductOptionInterface::class);
        $option->getTranslation('en_US')->willReturn($translation);

        self::assertSame(
            'Size',
            $this->resolver(option: $option->reveal())->resolve(new Facet('opt_t_shirt_size', 'array')),
        );
    }

    /**
     * @test
     */
    public function it_humanizes_the_facet_name_when_no_translation_exists(): void
    {
        self::assertSame('Brand', $this->resolver()->resolve(new Facet('brand', 'array')));
    }

    /**
     * @test
     */
    public function it_humanizes_a_dynamic_field_when_the_subject_no_longer_exists(): void
    {
        self::assertSame('Attr material', $this->resolver()->resolve(new Facet('attr_material', 'array')));
    }
}
