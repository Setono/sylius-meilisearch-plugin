<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\DataMapper\Taxon;

use Setono\SyliusMeilisearchPlugin\DataMapper\DataMapperInterface;
use Setono\SyliusMeilisearchPlugin\Document\Document;
use Setono\SyliusMeilisearchPlugin\Document\Taxon;
use Setono\SyliusMeilisearchPlugin\IndexScope\IndexScope;
use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Webmozart\Assert\Assert;

final class TaxonDataMapper implements DataMapperInterface
{
    /**
     * @param Taxon|Document $target
     * @param array<string, mixed> $context
     */
    public function map(IndexableInterface $source, Document $target, IndexScope $indexScope, array $context = []): void
    {
        Assert::true(
            $this->supports($source, $target, $indexScope, $context),
            'The given $source and $target is not supported',
        );

        $target->name = $source->getTranslation($indexScope->localeCode)->getName();
    }

    /**
     * @psalm-assert-if-true TaxonInterface $source
     * @psalm-assert-if-true Taxon $target
     * @psalm-assert-if-true !null $indexScope->localeCode
     */
    public function supports(
        IndexableInterface $source,
        Document $target,
        IndexScope $indexScope,
        array $context = [],
    ): bool {
        return $source instanceof TaxonInterface &&
            $target instanceof Taxon &&
            null !== $indexScope->localeCode;
    }
}
