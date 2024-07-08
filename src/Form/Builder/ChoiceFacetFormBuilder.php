<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Form\Builder;

use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use function Symfony\Component\String\u;

final class ChoiceFacetFormBuilder implements FacetFormBuilderInterface
{
    public function build(FormBuilderInterface $builder, string $name, array $values, array $stats = null): void
    {
        $keys = array_keys($values);
        $choices = array_combine($keys, $keys);

        $builder->add($name, ChoiceType::class, [
            'label' => sprintf('setono_sylius_meilisearch.form.search.facet.%s', u($name)->snake()),
            'choices' => $choices,
            'expanded' => true,
            'multiple' => true,
            'required' => false,
        ]);
    }

    public function supports(string $name, array $values, array $stats = null): bool
    {
        foreach (array_keys($values) as $value) {
            if (is_numeric($value)) {
                return false;
            }
        }

        return true;

    }
}
