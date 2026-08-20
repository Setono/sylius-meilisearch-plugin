<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\EventSubscriber\Search;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusMeilisearchPlugin\Engine\SearchRequest;
use Setono\SyliusMeilisearchPlugin\Event\Search\SearchFiltersBuilt;
use Setono\SyliusMeilisearchPlugin\Event\Search\SearchRequestCreated;
use Setono\SyliusMeilisearchPlugin\Event\Search\SearchResponseParametersCreated;
use Setono\SyliusMeilisearchPlugin\EventSubscriber\Search\TaxonSearchSubscriber;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Component\Taxonomy\Model\TaxonInterface;
use Sylius\Component\Taxonomy\Repository\TaxonRepositoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @covers \Setono\SyliusMeilisearchPlugin\EventSubscriber\Search\TaxonSearchSubscriber
 */
final class TaxonSearchSubscriberTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_filters_by_taxon_on_the_taxon_route(): void
    {
        $taxon = $this->prophesize(TaxonInterface::class);
        $taxon->getCode()->willReturn('JEANS');

        $subscriber = $this->createSubscriber($taxon->reveal());
        $subscriber->setTaxon($this->createSearchRequestCreated([
            '_route' => 'sylius_shop_product_index',
            'slug' => 'jeans',
        ]));

        $filtersBuilt = new SearchFiltersBuilt([]);
        $subscriber->updateFilters($filtersBuilt);
        self::assertSame(['taxonCodes = "JEANS"'], $filtersBuilt->filters);

        $responseParametersCreated = $this->createSearchResponseParametersCreated();
        $subscriber->updateResponseContext($responseParametersCreated);
        self::assertSame($taxon->reveal(), $responseParametersCreated->context['taxon'] ?? null);
    }

    /**
     * @test
     */
    public function it_ignores_other_routes(): void
    {
        $taxonRepository = $this->prophesize(TaxonRepositoryInterface::class);
        $taxonRepository->findOneBySlug(Argument::cetera())->shouldNotBeCalled();

        $localeContext = $this->prophesize(LocaleContextInterface::class);
        $localeContext->getLocaleCode()->willReturn('en_US');

        $subscriber = new TaxonSearchSubscriber($taxonRepository->reveal(), $localeContext->reveal());
        $subscriber->setTaxon($this->createSearchRequestCreated([
            '_route' => 'setono_sylius_meilisearch_shop_search',
            'slug' => 'jeans',
        ]));

        $filtersBuilt = new SearchFiltersBuilt([]);
        $subscriber->updateFilters($filtersBuilt);
        self::assertSame([], $filtersBuilt->filters);

        $responseParametersCreated = $this->createSearchResponseParametersCreated();
        $subscriber->updateResponseContext($responseParametersCreated);
        self::assertArrayNotHasKey('taxon', $responseParametersCreated->context);
    }

    /**
     * @test
     */
    public function it_ignores_requests_without_a_slug(): void
    {
        $taxonRepository = $this->prophesize(TaxonRepositoryInterface::class);
        $taxonRepository->findOneBySlug(Argument::cetera())->shouldNotBeCalled();

        $localeContext = $this->prophesize(LocaleContextInterface::class);
        $localeContext->getLocaleCode()->willReturn('en_US');

        $subscriber = new TaxonSearchSubscriber($taxonRepository->reveal(), $localeContext->reveal());
        $subscriber->setTaxon($this->createSearchRequestCreated([
            '_route' => 'sylius_shop_product_index',
        ]));

        $filtersBuilt = new SearchFiltersBuilt([]);
        $subscriber->updateFilters($filtersBuilt);
        self::assertSame([], $filtersBuilt->filters);
    }

    /**
     * @test
     */
    public function it_throws_a_not_found_exception_for_an_unknown_slug(): void
    {
        $localeContext = $this->prophesize(LocaleContextInterface::class);
        $localeContext->getLocaleCode()->willReturn('en_US');

        $taxonRepository = $this->prophesize(TaxonRepositoryInterface::class);
        $taxonRepository->findOneBySlug('does-not-exist', 'en_US')->willReturn(null);

        $subscriber = new TaxonSearchSubscriber($taxonRepository->reveal(), $localeContext->reveal());

        $this->expectException(NotFoundHttpException::class);

        $subscriber->setTaxon($this->createSearchRequestCreated([
            '_route' => 'sylius_shop_product_index',
            'slug' => 'does-not-exist',
        ]));
    }

    /**
     * @test
     */
    public function it_resets_its_state(): void
    {
        $taxon = $this->prophesize(TaxonInterface::class);
        $taxon->getCode()->willReturn('JEANS');

        $subscriber = $this->createSubscriber($taxon->reveal());
        $subscriber->setTaxon($this->createSearchRequestCreated([
            '_route' => 'sylius_shop_product_index',
            'slug' => 'jeans',
        ]));

        $subscriber->reset();

        $filtersBuilt = new SearchFiltersBuilt([]);
        $subscriber->updateFilters($filtersBuilt);
        self::assertSame([], $filtersBuilt->filters);
    }

    private function createSubscriber(TaxonInterface $taxon): TaxonSearchSubscriber
    {
        $localeContext = $this->prophesize(LocaleContextInterface::class);
        $localeContext->getLocaleCode()->willReturn('en_US');

        $taxonRepository = $this->prophesize(TaxonRepositoryInterface::class);
        $taxonRepository->findOneBySlug('jeans', 'en_US')->willReturn($taxon);

        return new TaxonSearchSubscriber($taxonRepository->reveal(), $localeContext->reveal());
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createSearchRequestCreated(array $attributes): SearchRequestCreated
    {
        $request = new Request(attributes: $attributes);

        return new SearchRequestCreated($request, SearchRequest::fromRequest($request));
    }

    private function createSearchResponseParametersCreated(): SearchResponseParametersCreated
    {
        return new SearchResponseParametersCreated('template.html.twig', [], new Request());
    }
}
