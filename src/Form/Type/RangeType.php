<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class RangeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('min', NumberType::class, [
                'label' => 'setono_sylius_meilisearch.form.search.range.min',
                'empty_data' => $options['min'] ?? null,
                // data-default lets search.js drop the value when it equals the default bound,
                // so an untouched range filter contributes no f[<facet>][min] param to the URL.
                'attr' => ['data-default' => $options['min']],
            ])
            ->add('max', NumberType::class, [
                'label' => 'setono_sylius_meilisearch.form.search.range.max',
                'empty_data' => $options['max'] ?? null,
                'attr' => ['data-default' => $options['max']],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults([
                'compound' => true,
                'min' => null,
                'max' => null,
            ])
            ->setAllowedTypes('min', ['null', 'int', 'float'])
            ->setAllowedTypes('max', ['null', 'int', 'float'])
        ;
    }

    public function getBlockPrefix(): string
    {
        return 'setono_sylius_meilisearch_range';
    }
}
