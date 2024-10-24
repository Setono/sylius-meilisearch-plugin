<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\DataMapper\Product;

use Setono\SyliusMeilisearchPlugin\DataMapper\DataMapperInterface;
use Setono\SyliusMeilisearchPlugin\Document\Document;
use Setono\SyliusMeilisearchPlugin\Document\Product as ProductDocument;
use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScope;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Webmozart\Assert\Assert;

final class TaxonsDataMapper implements DataMapperInterface
{
    public function __construct(
        private readonly int $levelsToInclude = 3,
        private readonly bool $includeRootLevel = false,
    ) {
    }

    public function map(IndexableInterface $source, Document $target, IndexScope $indexScope, array $context = []): void
    {
        Assert::true($this->supports($source, $target, $indexScope, $context), 'The given $source and $target is not supported');

        $taxons = array_filter([$source->getMainTaxon(), ...$source->getTaxons()->toArray()]);

        $breadcrumbs = [];

        foreach ($taxons as $taxon) {
            $breadcrumbs[] = $this->generateBreadcrumb($taxon, $indexScope->localeCode);
        }

        $target->taxons = array_values(array_unique($breadcrumbs));
    }

    private function generateBreadcrumb(TaxonInterface $taxon, ?string $localeCode): string
    {
        $taxons = [$taxon->getTranslation($localeCode)->getName()];
        foreach ($taxon->getAncestors() as $ancestor) {
            array_unshift($taxons, $ancestor->getTranslation($localeCode)->getName());
        }

        $taxons = array_filter($taxons, static function (?string $taxon) {
            return ((string) $taxon) !== '';
        });

        $taxons = array_slice($taxons, $this->includeRootLevel ? 0 : 1, $this->levelsToInclude);

        return implode(' > ', $taxons);
    }

    /**
     * @psalm-assert-if-true ProductInterface $source
     * @psalm-assert-if-true ProductDocument $target
     */
    public function supports(IndexableInterface $source, Document $target, IndexScope $indexScope, array $context = []): bool
    {
        return $source instanceof ProductInterface && $target instanceof ProductDocument;
    }
}
