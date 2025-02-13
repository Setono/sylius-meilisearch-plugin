<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Form\Builder;

use Setono\SyliusMeilisearchPlugin\Document\Metadata\Facet;
use Setono\SyliusMeilisearchPlugin\Engine\FacetStat;
use Setono\SyliusMeilisearchPlugin\Engine\FacetValues;
use Symfony\Component\Form\FormBuilderInterface;

interface FilterFormBuilderInterface
{
    public function build(FormBuilderInterface $builder, Facet $facet, FacetValues $values, FacetStat $stats = null): void;

    public function supports(Facet $facet, FacetValues $values, FacetStat $stats = null): bool;
}
