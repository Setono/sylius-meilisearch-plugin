<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Form\Type;

use Setono\SyliusMeilisearchPlugin\Config\IndexRegistryInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class IndexChoiceType extends AbstractType
{
    public function __construct(private readonly IndexRegistryInterface $indexRegistry)
    {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $names = $this->indexRegistry->getNames();

        $resolver->setDefaults([
            'choices' => array_combine($names, $names),
            'choice_label' => static fn (string $name): string => ucfirst($name),
        ]);
    }

    public function getParent(): string
    {
        return ChoiceType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'setono_sylius_meilisearch_index_choice';
    }
}
