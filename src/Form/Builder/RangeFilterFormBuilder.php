<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Form\Builder;

use Setono\SyliusMeilisearchPlugin\Document\Metadata\Facet;
use Setono\SyliusMeilisearchPlugin\Engine\FacetValues;
use Setono\SyliusMeilisearchPlugin\Form\Type\RangeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class RangeFilterFormBuilder implements FilterFormBuilderInterface
{
    use FacetLabelTrait;

    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function build(FormBuilderInterface $builder, Facet $facet, FacetValues $values): void
    {
        $builder->add($facet->name, RangeType::class, [
            'label' => $this->facetLabel($this->translator, $facet),
            'required' => false,
            'block_prefix' => 'setono_sylius_meilisearch_facet_range',
            'priority' => -1 * $facet->position,
            'min' => $values->stats?->min,
            'max' => $values->stats?->max,
        ]);
    }

    public function supports(Facet $facet, FacetValues $values): bool
    {
        return null !== $values->stats && in_array($facet->type, ['float', 'int'], true);
    }
}
