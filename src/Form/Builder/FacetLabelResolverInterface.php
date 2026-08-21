<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Form\Builder;

use Setono\SyliusMeilisearchPlugin\Document\Metadata\Facet;

interface FacetLabelResolverInterface
{
    /**
     * Returns the label for the given facet: either a translation key or a literal label
     * (Symfony's label translation passes a string that is not in the catalogue through unchanged)
     */
    public function resolve(Facet $facet): string;
}
