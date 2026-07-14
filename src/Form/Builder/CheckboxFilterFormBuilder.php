<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Form\Builder;

use Setono\SyliusMeilisearchPlugin\Document\Metadata\Facet;
use Setono\SyliusMeilisearchPlugin\Engine\FacetValues;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class CheckboxFilterFormBuilder implements FilterFormBuilderInterface
{
    use FacetLabelTrait;

    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function build(FormBuilderInterface $builder, Facet $facet, FacetValues $values): void
    {
        $builder->add($facet->name, CheckboxType::class, [
            'label' => $this->facetLabel($this->translator, $facet),
            'label_translation_parameters' => [
                '%count%' => $values['true'],
            ],
            'required' => false,
            'block_prefix' => 'setono_sylius_meilisearch_facet_checkbox',
            'priority' => -1 * $facet->position,
        ]);
    }

    public function supports(Facet $facet, FacetValues $values): bool
    {
        return $facet->type === 'bool' && match (count($values)) {
            1 => isset($values['true']),
            2 => isset($values['true'], $values['false']),
            default => false,
        };
    }
}
