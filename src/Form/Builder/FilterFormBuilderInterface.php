<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Form\Builder;

use Setono\SyliusMeilisearchPlugin\Document\Metadata\Facet;
use Setono\SyliusMeilisearchPlugin\Engine\FacetValues;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Builds the form widget for a single facet (a checkbox, a multi-choice list, a range, …). The
 * shipped builders cover the common types; implement this interface and tag the service with
 * "setono_sylius_meilisearch.filter_form_builder" (autoconfiguration adds the tag) to add your own.
 *
 * Builders are ordered by tag priority: the first one whose supports() returns true for a given
 * facet is used, so give a more specific builder a higher priority than the shipped ones.
 */
interface FilterFormBuilderInterface
{
    /**
     * Adds a child to $builder — named after $facet->name — that renders and submits the filter for
     * this facet. It is only called when supports() returned true for the same $facet/$values.
     */
    public function build(FormBuilderInterface $builder, Facet $facet, FacetValues $values): void;

    /**
     * Whether this builder can build a widget for the given facet and its available values (e.g.
     * based on $facet->type and the number/shape of $values).
     */
    public function supports(Facet $facet, FacetValues $values): bool;
}
