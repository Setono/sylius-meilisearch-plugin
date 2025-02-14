<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Form\Builder;

use Setono\SyliusMeilisearchPlugin\Document\Metadata\Facet;
use Setono\SyliusMeilisearchPlugin\Engine\FacetValues;
use Setono\SyliusMeilisearchPlugin\Form\Type\RangeType;
use Symfony\Component\Form\FormBuilderInterface;
use function Symfony\Component\String\u;

final class RangeFilterFormBuilder implements FilterFormBuilderInterface
{
    public function build(FormBuilderInterface $builder, Facet $facet, FacetValues $values): void
    {
        $builder->add($facet->name, RangeType::class, [
            'label' => sprintf('setono_sylius_meilisearch.form.search.facet.%s', u($facet->name)->snake()),
            'required' => false,
            'block_prefix' => 'setono_sylius_meilisearch_facet_range',
            'priority' => -1 * $facet->position,
        ]);
    }

    public function supports(Facet $facet, FacetValues $values): bool
    {
        return null !== $values->stats && in_array($facet->type, ['float', 'int'], true);
    }
}
