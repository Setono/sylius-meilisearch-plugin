<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Form\Type;

use Setono\SyliusMeilisearchPlugin\Config\IndexRegistryInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class IndexChoiceType extends AbstractType
{
    public function __construct(private readonly IndexRegistryInterface $indexRegistry)
    {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // when true, only indexes that support dynamic fields are offered - used by the
            // indexable attribute/option forms where targeting any other index would be dead configuration
            'dynamic_fields_only' => false,
            'choices' => function (Options $options): array {
                $names = [];
                foreach ($this->indexRegistry as $index) {
                    if (true === $options['dynamic_fields_only'] && !$index->supportsDynamicFields()) {
                        continue;
                    }

                    $names[] = $index->name;
                }

                return array_combine($names, $names);
            },
            // not ucfirst(...): choice_label is called with ($choice, $key, $value), and a
            // first-class callable to an internal function throws ArgumentCountError on the
            // surplus arguments, where a closure absorbs them
            'choice_label' => static fn (string $name): string => ucfirst($name),
        ]);

        $resolver->setAllowedTypes('dynamic_fields_only', 'bool');
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
