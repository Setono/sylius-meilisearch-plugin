<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Resolver\SortBy;

use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\Resolver\IndexName\IndexNameResolverInterface;
use Setono\SyliusMeilisearchPlugin\Resolver\ReplicaIndexName\ReplicaIndexNameResolverInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class SortByResolver implements SortByResolverInterface
{
    public function __construct(private readonly IndexNameResolverInterface $indexNameResolver, private readonly ReplicaIndexNameResolverInterface $replicaIndexNameResolver, private readonly TranslatorInterface $translator, private readonly LocaleContextInterface $localeContext)
    {
    }

    public function resolveFromIndexableResource(Index $index, string $locale = null): array
    {
        $locale ??= $this->localeContext->getLocaleCode();

        $indexName = $this->indexNameResolver->resolve($index);

        $sortBys = [
            new SortBy(
                $this->translator->trans('setono_sylius_meilisearch.ui.sort_by.relevance', [], null, $locale),
                $indexName,
            ),
        ];

        foreach ($index->document::getSortableAttributes() as $attribute => $order) {
            $sortBys[] = new SortBy(
                $this->translator->trans(sprintf('setono_sylius_meilisearch.ui.sort_by.%s_%s', $attribute, $order), [], null, $locale),
                $this->replicaIndexNameResolver->resolveFromIndexNameAndSortableAttribute(
                    $indexName,
                    $attribute,
                    $order,
                ),
            );
        }

        return $sortBys;
    }
}
