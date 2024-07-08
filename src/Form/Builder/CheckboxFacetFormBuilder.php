<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Form\Builder;

use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use function Symfony\Component\String\u;

final class CheckboxFacetFormBuilder implements FacetFormBuilderInterface
{
    public function build(FormBuilderInterface $builder, string $name, array $values, array $stats = null): void
    {
        $builder->add($name, CheckboxType::class, [
            'label' => sprintf('setono_sylius_meilisearch.form.search.facet.%s', u($name)->snake()),
            'required' => false,
        ]);
    }

    public function supports(string $name, array $values, array $stats = null): bool
    {
        $c = count($values);

        return match ($c) {
            1 => isset($values['true']) || isset($values['false']),
            2 => isset($values['true'], $values['false']),
            default => false,
        };
    }
}
