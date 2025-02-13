<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Form\Builder;

use Setono\SyliusMeilisearchPlugin\Document\Metadata\Facet;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use function Symfony\Component\String\u;

final class CheckboxFilterFormBuilder implements FilterFormBuilderInterface
{
    public function build(FormBuilderInterface $builder, Facet $facet, array $values, array $stats = null): void
    {
        $builder->add($facet->name, CheckboxType::class, [
            'label' => sprintf('setono_sylius_meilisearch.form.search.facet.%s', u($facet->name)->snake()),
            'label_translation_parameters' => [
                '%count%' => $values['true'],
            ],
            'required' => false,
            'block_prefix' => 'setono_sylius_meilisearch_facet_checkbox',
            'priority' => -1 * $facet->position,
        ]);
    }

    public function supports(Facet $facet, array $values, array $stats = null): bool
    {
        return $facet->type === 'bool' && match (count($values)) {
            1 => isset($values['true']),
            2 => isset($values['true'], $values['false']),
            default => false,
        };
    }
}
