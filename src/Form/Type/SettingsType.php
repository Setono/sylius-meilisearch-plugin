<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Form\Type;

use Setono\SyliusMeilisearchPlugin\Settings\Settings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class SettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $reflection = new \ReflectionClass(Settings::class);
        $properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);
        foreach ($properties as $property) {
            $builder->add($property->getName());
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Settings::class,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'setono_sylius_meilisearch_settings';
    }
}
