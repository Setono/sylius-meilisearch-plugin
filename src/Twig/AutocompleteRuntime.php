<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Twig;

use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\Meilisearch\Autocomplete\Configuration\Configuration;
use Setono\SyliusMeilisearchPlugin\Meilisearch\Autocomplete\Configuration\Source;
use Setono\SyliusMeilisearchPlugin\Resolver\IndexName\IndexNameResolverInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\RuntimeExtensionInterface;

final class AutocompleteRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly IndexNameResolverInterface $indexNameResolver,
        private readonly TranslatorInterface $translator,
        private readonly string $host,
        private readonly string $searchKey,
        private readonly string $container,
        private readonly string $placeholder,
        /** @var list<Index> $indexes */
        private readonly array $indexes,
    ) {
    }

    public function configuration(): Configuration
    {
        $configuration = new Configuration(
            $this->host,
            $this->searchKey,
            $this->container,
            $this->translator->trans($this->placeholder),
        );

        foreach ($this->indexes as $index) {
            // todo resolving the source should be extracted to a service
            $configuration->sources[] = new Source($index->name, $this->indexNameResolver->resolve($index), 'url');
        }

        return $configuration;
    }
}
