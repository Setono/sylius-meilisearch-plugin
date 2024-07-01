<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Provider\IndexScope;

use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\IndexScope\IndexScope;
use Sylius\Component\Core\Model\TaxonInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;

final class TaxonIndexScopeProvider implements IndexScopeProviderInterface
{
    public function __construct(
        private readonly RepositoryInterface $localeRepository,
        private readonly LocaleContextInterface $localeContext,
    ) {
    }

    public function getAll(Index $index): iterable
    {
        /** @var LocaleInterface[] $locales */
        $locales = $this->localeRepository->findAll();

        foreach ($locales as $locale) {
            yield new IndexScope(index: $index, localeCode: $locale->getCode());
        }
    }

    public function getFromContext(Index $index): IndexScope
    {
        return $this->getFromChannelAndLocaleAndCurrency(
            $index,
            null,
            $this->localeContext->getLocaleCode(),
        );
    }

    public function getFromChannelAndLocaleAndCurrency(
        Index $index,
        string $channelCode = null,
        string $localeCode = null,
        string $currencyCode = null,
    ): IndexScope {
        return new IndexScope(index: $index, localeCode: $localeCode);
    }

    public function supports(Index $index): bool
    {
        return $index->hasResourceWithClass(TaxonInterface::class);
    }
}
