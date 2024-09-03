<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;

final class RangeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('min', NumberType::class, [
                'label' => 'Min',
            ])
            ->add('max', NumberType::class, [
                'label' => 'Max',
            ])
        ;
    }

    public function getBlockPrefix(): string
    {
        return 'setono_sylius_meilisearch_range';
    }
}
