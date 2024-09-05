<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Form\Builder;

use Setono\SyliusMeilisearchPlugin\Document\Metadata\Facet;
use Setono\SyliusMeilisearchPlugin\Form\Builder\Sorter\ChoiceFacetFormSorter;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use function Symfony\Component\String\u;

final class ChoiceFacetFormBuilder implements FacetFormBuilderInterface
{
    public function build(FormBuilderInterface $builder, Facet $facet, array $values, array $stats = null): void
    {
        $keys = array_keys($values);
        $choices = array_combine($keys, $keys);

        if ($facet->valuesOrder !== []) {
            $choices = ChoiceFacetFormSorter::sort($choices, $facet->valuesOrder);
        }

        $builder->add($facet->name, ChoiceType::class, [
            'label' => sprintf('setono_sylius_meilisearch.form.search.facet.%s', u($facet->name)->snake()),
            'choices' => $choices,
            'choice_label' => fn (string $key) => sprintf('%s (%d)', $key, $values[$key]),
            'expanded' => true,
            'multiple' => true,
            'required' => false,
            'block_prefix' => 'setono_sylius_meilisearch_facet_choice',
        ]);
    }

    public function supports(Facet $facet, array $values, array $stats = null): bool
    {
        if ($facet->type !== 'array') {
            return false;
        }

        $keys = array_keys($values);
        if (count($keys) < 2) {
            return false;
        }

        foreach ($keys as $key) {
            if (is_numeric($key)) {
                return false;
            }
        }

        return true;
    }
}
