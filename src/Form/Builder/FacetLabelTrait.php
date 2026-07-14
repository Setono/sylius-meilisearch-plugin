<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Form\Builder;

use Setono\SyliusMeilisearchPlugin\Document\Metadata\Facet;
use function Symfony\Component\String\u;
use Symfony\Contracts\Translation\TranslatorInterface;

trait FacetLabelTrait
{
    /**
     * Resolves the label for a facet: the translation key when the catalogue defines it (so
     * projects can override it), otherwise a humanized version of the facet name so that facets
     * without a shipped translation (e.g. "brand") don't render their raw translation key.
     */
    private function facetLabel(TranslatorInterface $translator, Facet $facet): string
    {
        $name = u($facet->name)->snake()->toString();
        $key = sprintf('setono_sylius_meilisearch.form.search.facet.%s', $name);

        // When the translator returns the key unchanged there is no translation for it, so fall
        // back to a humanized facet name rather than rendering the raw key.
        if ($translator->trans($key) !== $key) {
            return $key;
        }

        return ucfirst(str_replace('_', ' ', $name));
    }
}
