<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Meilisearch\Autocomplete;

use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\Meilisearch\Autocomplete\Configuration\Source;
use Setono\SyliusMeilisearchPlugin\Resolver\IndexUid\IndexUidResolverInterface;
use Twig\Environment;

final class DefaultSourceResolver implements SourceResolverInterface
{
    public function __construct(
        private readonly IndexUidResolverInterface $indexUidResolver,
        private readonly Environment $twig,
        private readonly int $limit,
        /** The attribute that holds the URL on any given document. Decorate this resolver to vary it per index. */
        private readonly string $urlAttribute = 'url',
    ) {
    }

    public function resolve(Index $index): Source
    {
        return new Source(
            $index->name,
            $this->indexUidResolver->resolve($index),
            $this->urlAttribute,
            [
                'item' => $this->twig->render($this->resolveItemTemplate($index)),
            ],
            $this->limit,
        );
    }

    /**
     * Resolves the item template for the given index, preferring a per-index override
     * (autocomplete/templates/{indexName}/item.html.twig) and falling back to the shared template.
     */
    private function resolveItemTemplate(Index $index): string
    {
        $indexTemplate = sprintf('@SetonoSyliusMeilisearchPlugin/autocomplete/templates/%s/item.html.twig', $index->name);

        if ($this->twig->getLoader()->exists($indexTemplate)) {
            return $indexTemplate;
        }

        return '@SetonoSyliusMeilisearchPlugin/autocomplete/templates/item.html.twig';
    }
}
