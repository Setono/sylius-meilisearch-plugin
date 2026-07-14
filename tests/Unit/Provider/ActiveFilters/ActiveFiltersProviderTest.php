<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\Provider\ActiveFilters;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Container\ContainerInterface;
use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\Facet;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\Metadata;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\MetadataFactoryInterface;
use Setono\SyliusMeilisearchPlugin\Document\Product;
use Setono\SyliusMeilisearchPlugin\Engine\FacetDistribution;
use Setono\SyliusMeilisearchPlugin\Engine\SearchResult;
use Setono\SyliusMeilisearchPlugin\Provider\ActiveFilters\ActiveFiltersProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Translation\MessageCatalogueInterface;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Webmozart\Assert\Assert;

/**
 * @covers \Setono\SyliusMeilisearchPlugin\Provider\ActiveFilters\ActiveFiltersProvider
 */
final class ActiveFiltersProviderTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_provides_no_active_filters_when_no_filters_are_applied(): void
    {
        $activeFilters = $this->createProvider()->provide(Request::create('/search?q=jeans'));

        self::assertTrue($activeFilters->isEmpty());
        self::assertCount(0, $activeFilters);
        self::assertNull($activeFilters->resetUrl);
    }

    /**
     * @test
     */
    public function it_provides_choice_filters(): void
    {
        $request = Request::create('/search?q=jeans&f[brand][]=Celsius Small&f[brand][]=You are breathtaking&p=2');

        $activeFilters = $this->createProvider()->provide($request);

        self::assertCount(2, $activeFilters);

        [$first, $second] = $activeFilters->filters;

        self::assertSame('brand', $first->facet);
        self::assertSame('Celsius Small', $first->label);
        self::assertSame('Celsius Small', $first->value);
        self::assertSame('/search?q=jeans&f%5Bbrand%5D%5B0%5D=You+are+breathtaking', $first->removeUrl);

        self::assertSame('You are breathtaking', $second->label);
        self::assertSame('/search?q=jeans&f%5Bbrand%5D%5B0%5D=Celsius+Small', $second->removeUrl);
    }

    /**
     * @test
     */
    public function it_provides_choice_filter_from_scalar_value(): void
    {
        $activeFilters = $this->createProvider()->provide(Request::create('/search?q=jeans&f[brand]=Celsius Small'));

        self::assertCount(1, $activeFilters);
        self::assertSame('Celsius Small', $activeFilters->filters[0]->label);
        self::assertSame('/search?q=jeans', $activeFilters->filters[0]->removeUrl);
    }

    /**
     * @test
     */
    public function it_skips_empty_and_non_string_choice_values(): void
    {
        $activeFilters = $this->createProvider()->provide(Request::create('/search?f[brand][]=&f[brand][sub][]=nested'));

        self::assertTrue($activeFilters->isEmpty());
    }

    /**
     * @test
     */
    public function it_provides_boolean_filter_when_truthy(): void
    {
        $activeFilters = $this->createProvider()->provide(Request::create('/search?q=jeans&f[onSale]=1'));

        self::assertCount(1, $activeFilters);
        self::assertSame('onSale', $activeFilters->filters[0]->facet);
        self::assertSame('setono_sylius_meilisearch.form.search.active_filters.facet.on_sale', $activeFilters->filters[0]->label);
        self::assertNull($activeFilters->filters[0]->value);
        self::assertSame('/search?q=jeans', $activeFilters->filters[0]->removeUrl);
    }

    /**
     * @test
     */
    public function it_provides_no_boolean_filter_when_falsy(): void
    {
        $activeFilters = $this->createProvider()->provide(Request::create('/search?q=jeans&f[onSale]=0'));

        self::assertTrue($activeFilters->isEmpty());
    }

    /**
     * @test
     */
    public function it_humanizes_facet_name_when_translation_is_missing(): void
    {
        $activeFilters = $this->createProvider(
            facets: ['myCustom' => new Facet('myCustom', 'bool')],
            translatedKeys: [],
        )->provide(Request::create('/search?f[myCustom]=true'));

        self::assertCount(1, $activeFilters);
        self::assertSame('My custom', $activeFilters->filters[0]->label);
    }

    /**
     * @test
     */
    public function it_provides_range_filter_when_bounds_narrow_the_facet_stats(): void
    {
        $request = Request::create('/search?q=jeans&f[price][min]=10&f[price][max]=50');

        $activeFilters = $this->createProvider()->provide($request, $this->createSearchResult(5.49, 99.99));

        self::assertCount(1, $activeFilters);
        self::assertSame('price', $activeFilters->filters[0]->facet);
        self::assertSame('setono_sylius_meilisearch.form.search.active_filters.range', $activeFilters->filters[0]->label);
        self::assertSame('/search?q=jeans', $activeFilters->filters[0]->removeUrl);
    }

    /**
     * @test
     */
    public function it_mentions_only_narrowing_bounds_in_range_filter_label(): void
    {
        // the max equals the facet's upper bound, i.e. the untouched, prefilled value submitted by the search form
        $request = Request::create('/search?q=jeans&f[price][min]=50&f[price][max]=99.99');

        $activeFilters = $this->createProvider()->provide($request, $this->createSearchResult(5.49, 99.99));

        self::assertCount(1, $activeFilters);
        self::assertSame('setono_sylius_meilisearch.form.search.active_filters.range_min', $activeFilters->filters[0]->label);
    }

    /**
     * @test
     */
    public function it_provides_no_range_filter_when_no_bound_narrows_the_facet_stats(): void
    {
        // both bounds equal the facet's bounds, i.e. the untouched, prefilled values submitted by the search form
        $request = Request::create('/search?q=jeans&f[price][min]=5.49&f[price][max]=99.99');

        $activeFilters = $this->createProvider()->provide($request, $this->createSearchResult(5.49, 99.99));

        self::assertTrue($activeFilters->isEmpty());
        self::assertNull($activeFilters->resetUrl);
    }

    /**
     * @test
     */
    public function it_provides_range_filter_when_stats_are_unavailable(): void
    {
        $request = Request::create('/search?q=jeans&f[price][min]=10');

        $activeFilters = $this->createProvider()->provide($request);

        self::assertCount(1, $activeFilters);
        self::assertSame('setono_sylius_meilisearch.form.search.active_filters.range_min', $activeFilters->filters[0]->label);
    }

    /**
     * @test
     */
    public function it_ignores_invalid_range_bounds(): void
    {
        $activeFilters = $this->createProvider()->provide(Request::create('/search?f[price][min]=abc&f[price][max]='));

        self::assertTrue($activeFilters->isEmpty());
    }

    /**
     * @test
     */
    public function it_skips_facets_that_are_not_defined_in_the_metadata(): void
    {
        $activeFilters = $this->createProvider()->provide(Request::create('/search?f[unknown][]=value'));

        self::assertTrue($activeFilters->isEmpty());
    }

    /**
     * @test
     */
    public function it_provides_reset_url_that_keeps_unrelated_parameters(): void
    {
        $request = Request::create('/search?q=jeans&s=price:asc&utm_source=newsletter&f[brand][]=Celsius Small&f[onSale]=1&p=2');

        $activeFilters = $this->createProvider()->provide($request);

        self::assertCount(2, $activeFilters);
        self::assertSame('/search?q=jeans&s=price%3Aasc&utm_source=newsletter', $activeFilters->resetUrl);
    }

    /**
     * @test
     */
    public function it_preserves_the_path_of_the_request(): void
    {
        $request = Request::create('/shop/t/mens/jeans?f[brand][]=Celsius Small');

        $activeFilters = $this->createProvider()->provide($request);

        self::assertCount(1, $activeFilters);
        self::assertSame('/shop/t/mens/jeans', $activeFilters->filters[0]->removeUrl);
        self::assertSame('/shop/t/mens/jeans', $activeFilters->resetUrl);
    }

    /**
     * @param array<string, Facet>|null $facets
     * @param list<string>|null $translatedKeys keys that exist in the translation catalogue (null means all keys exist)
     */
    private function createProvider(?array $facets = null, ?array $translatedKeys = null): ActiveFiltersProvider
    {
        $metadata = new Metadata(Product::class);
        $metadata->facetableAttributes = $facets ?? [
            'brand' => new Facet('brand', 'array'),
            'onSale' => new Facet('onSale', 'bool'),
            'price' => new Facet('price', 'float'),
        ];

        $metadataFactory = $this->prophesize(MetadataFactoryInterface::class);
        $metadataFactory->getMetadataFor(Product::class)->willReturn($metadata);

        $locator = $this->prophesize(ContainerInterface::class);
        $locator->get(MetadataFactoryInterface::class)->willReturn($metadataFactory->reveal());

        $catalogue = $this->prophesize(MessageCatalogueInterface::class);
        if (null === $translatedKeys) {
            $catalogue->has(Argument::type('string'))->willReturn(true);
        } else {
            $catalogue->has(Argument::type('string'))->will(static fn (array $args): bool => in_array($args[0], $translatedKeys, true));
        }

        $translator = $this->prophesize(TranslatorInterface::class);
        $translator->willImplement(TranslatorBagInterface::class);
        $translator->getCatalogue()->willReturn($catalogue->reveal());

        // return the translation key so the tests can assert which key was used
        $returnKey = static function (array $args): string {
            Assert::string($args[0]);

            return $args[0];
        };
        $translator->trans(Argument::type('string'))->will($returnKey);
        $translator->trans(Argument::type('string'), Argument::type('array'))->will($returnKey);

        return new ActiveFiltersProvider(
            new Index('search', Product::class, [], $locator->reveal()),
            $translator->reveal(),
        );
    }

    private function createSearchResult(float $min, float $max): SearchResult
    {
        return new SearchResult(
            new Index('search', Product::class, [], $this->prophesize(ContainerInterface::class)->reveal()),
            [],
            8,
            1,
            9,
            1,
            new FacetDistribution(
                ['price' => ['5.49' => 1, '99.99' => 1]],
                ['price' => ['min' => $min, 'max' => $max]],
            ),
        );
    }
}
