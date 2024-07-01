<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\DataMapper\Product;

use Setono\SyliusMeilisearchPlugin\DataMapper\DataMapperInterface;
use Setono\SyliusMeilisearchPlugin\Document\Document;
use Setono\SyliusMeilisearchPlugin\Document\Product as ProductDocument;
use Setono\SyliusMeilisearchPlugin\IndexScope\IndexScope;
use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Taxonomy\Model\TaxonInterface;
use Webmozart\Assert\Assert;

final class TaxonCodesDataMapper implements DataMapperInterface
{
    public function __construct(private readonly bool $includeDescendants)
    {
    }

    /**
     * @param ProductDocument|Document $target
     * @param array<string, mixed> $context
     */
    public function map(IndexableInterface $source, Document $target, IndexScope $indexScope, array $context = []): void
    {
        Assert::true($this->supports($source, $target, $indexScope, $context), 'The given $source and $target is not supported');

        $mainTaxon = $source->getMainTaxon();
        if (null !== $mainTaxon) {
            $this->populateTaxons($target, $mainTaxon);
        }

        foreach ($source->getTaxons() as $taxon) {
            $this->populateTaxons($target, $taxon);
        }

        $target->taxonCodes = array_values(array_unique($target->taxonCodes));
    }

    private function populateTaxons(ProductDocument $productDocument, TaxonInterface $taxon): void
    {
        $productDocument->taxonCodes[] = (string) $taxon->getCode();
        if ($this->includeDescendants) {
            foreach ($taxon->getAncestors() as $ancestor) {
                $productDocument->taxonCodes[] = (string) $ancestor->getCode();
            }
        }
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
