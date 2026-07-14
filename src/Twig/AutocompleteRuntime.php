<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Twig;

use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\Meilisearch\Autocomplete\Configuration\Configuration;
use Setono\SyliusMeilisearchPlugin\Meilisearch\Autocomplete\SourceResolverInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\RuntimeExtensionInterface;

final class AutocompleteRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly SourceResolverInterface $sourceResolver,
        private readonly TranslatorInterface $translator,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly string $host,
        private readonly string $searchKey,
        private readonly string $container,
        private readonly string $placeholder,
        /** @var list<Index> $indexes */
        private readonly array $indexes,
        private readonly bool $debug,
    ) {
    }

    public function configuration(): string
    {
        $configuration = new Configuration(
            host: $this->host,
            searchKey: $this->searchKey,
            container: $this->container,
            placeholder: $this->translator->trans($this->placeholder),
            searchPath: $this->urlGenerator->generate('setono_sylius_meilisearch_shop_search'),
            seeAllLabel: $this->translator->trans('setono_sylius_meilisearch.autocomplete.see_all_results'),
            debug: $this->debug,
        );

        foreach ($this->indexes as $index) {
            $configuration->sources[] = $this->sourceResolver->resolve($index);
        }

        return sprintf('<script type="application/json" id="ssm-autocomplete-configuration">%s</script>', json_encode($configuration, \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE));
    }
}
