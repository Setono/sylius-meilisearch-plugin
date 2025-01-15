<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\EventSubscriber\Search;

use Setono\SyliusMeilisearchPlugin\Event\Search\SearchFiltersBuilt;
use Setono\SyliusMeilisearchPlugin\Event\Search\SearchRequestCreated;
use Setono\SyliusMeilisearchPlugin\Event\Search\SearchResponseParametersCreated;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Component\Taxonomy\Model\TaxonInterface;
use Sylius\Component\Taxonomy\Repository\TaxonRepositoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Service\ResetInterface;

final class TaxonSearchSubscriber implements EventSubscriberInterface, ResetInterface
{
    private ?TaxonInterface $taxon = null;

    public function __construct(
        private readonly TaxonRepositoryInterface $taxonRepository,
        private readonly LocaleContextInterface $localeContext,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SearchRequestCreated::class => 'setTaxon',
            SearchResponseParametersCreated::class => 'updateResponseContext',
            SearchFiltersBuilt::class => 'updateFilters',
        ];
    }

    public function setTaxon(SearchRequestCreated $event): void
    {
        $route = $event->request->attributes->get('_route');
        if ('setono_sylius_meilisearch_shop_taxon' !== $route) {
            return;
        }

        $slug = $event->request->attributes->get('slug');
        if (!is_string($slug) || '' === $slug) {
            return;
        }

        $this->taxon = $this->taxonRepository->findOneBySlug($slug, $this->localeContext->getLocaleCode());
    }

    public function updateFilters(SearchFiltersBuilt $event): void
    {
        if (null === $this->taxon) {
            return;
        }

        $event->filters[] = sprintf('taxonCodes = "%s"', (string) $this->taxon->getCode());
    }

    public function updateResponseContext(SearchResponseParametersCreated $event): void
    {
        if (null === $this->taxon) {
            return;
        }

        $event->context['taxon'] = $this->taxon;
    }

    public function reset(): void
    {
        $this->taxon = null;
    }
}
