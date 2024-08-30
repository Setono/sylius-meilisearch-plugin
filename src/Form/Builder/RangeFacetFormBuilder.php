<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Form\Builder;

use Setono\SyliusMeilisearchPlugin\Document\Metadata\Facet;
use Setono\SyliusMeilisearchPlugin\Form\Type\RangeType;
use Symfony\Component\Form\FormBuilderInterface;
use function Symfony\Component\String\u;

final class RangeFacetFormBuilder implements FacetFormBuilderInterface
{
    public function build(FormBuilderInterface $builder, array $values, Facet $facet, array $stats = null): void
    {
        if ($stats === null || !isset($stats['min']) && !isset($stats['max'])) {
            return;
        }

        $builder->add($facet->name, RangeType::class, [
            'label' => sprintf('setono_sylius_meilisearch.form.search.facet.%s', u($facet->name)->snake()),
            'required' => false,
            'block_prefix' => 'setono_sylius_meilisearch_facet_range',
        ]);
    }

    public function supports(array $values, Facet $facet, array $stats = null): bool
    {
        return $facet->type === 'float';
    }
}
