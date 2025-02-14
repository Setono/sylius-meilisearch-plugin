<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Form\Builder;

use Setono\SyliusMeilisearchPlugin\Document\Metadata\Facet;
use Setono\SyliusMeilisearchPlugin\Engine\FacetValues;
use Setono\SyliusMeilisearchPlugin\Form\Builder\Sorter\FilterValuesSorterInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use function Symfony\Component\String\u;

final class ChoiceFilterFormBuilder implements FilterFormBuilderInterface
{
    public function build(FormBuilderInterface $builder, Facet $facet, FacetValues $values): void
    {
        $choices = array_combine($values->getValues(), $values->getValues());

        /** @var class-string<FilterValuesSorterInterface> $sorter */
        $sorter = $facet->sorter;
        if ($facet->sorter !== null) {
            /** @var array $choices */
            $choices = (new $sorter())->sort($choices);
        }

        $builder->add($facet->name, ChoiceType::class, [
            'label' => sprintf('setono_sylius_meilisearch.form.search.facet.%s', u($facet->name)->snake()),
            'choices' => $choices,
            'choice_label' => fn (string $value) => sprintf('%s (%d)', $value, $values->getValueCount($value)),
            'expanded' => true,
            'multiple' => true,
            'required' => false,
            'block_prefix' => 'setono_sylius_meilisearch_facet_choice',
            'priority' => -1 * $facet->position,
        ]);
    }

    public function supports(Facet $facet, FacetValues $values): bool
    {
        if ($facet->type !== 'array') {
            return false;
        }

        if (count($values) < 2) {
            return false;
        }

        foreach ($values->getValues() as $value) {
            if (is_numeric($value)) {
                return false;
            }
        }

        return true;
    }
}
