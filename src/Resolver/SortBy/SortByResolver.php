<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Resolver\SortBy;

use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\Resolver\IndexName\IndexNameResolverInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class SortByResolver implements SortByResolverInterface
{
    public function __construct(
        private readonly IndexNameResolverInterface $indexNameResolver,
        private readonly TranslatorInterface $translator,
        private readonly LocaleContextInterface $localeContext,
    ) {
    }

    public function resolveFromIndexableResource(Index $index, string $locale = null): array
    {
        $locale ??= $this->localeContext->getLocaleCode();

        $indexName = $this->indexNameResolver->resolve($index);

        return [
            new SortBy(
                $this->translator->trans('setono_sylius_meilisearch.ui.sort_by.relevance', [], null, $locale),
                $indexName,
            ),
        ];
    }
}
