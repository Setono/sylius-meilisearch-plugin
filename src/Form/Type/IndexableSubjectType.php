<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Form\Type;

use Sylius\Bundle\ResourceBundle\Form\Type\AbstractResourceType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * The shared form for the IndexableAttribute and IndexableOption resources
 */
abstract class IndexableSubjectType extends AbstractResourceType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('code', ChoiceType::class, [
                'choices' => $this->getCodeChoices(),
                'label' => $this->getCodeLabel(),
                'choice_translation_domain' => false,
            ])
            ->add('searchable', CheckboxType::class, [
                'label' => 'setono_sylius_meilisearch.form.indexable.searchable',
                'help' => 'setono_sylius_meilisearch.form.indexable.searchable_help',
                'required' => false,
            ])
            ->add('filterable', CheckboxType::class, [
                'label' => 'setono_sylius_meilisearch.form.indexable.filterable',
                'help' => 'setono_sylius_meilisearch.form.indexable.filterable_help',
                'required' => false,
            ])
            ->add('facetable', CheckboxType::class, [
                'label' => 'setono_sylius_meilisearch.form.indexable.facetable',
                'help' => 'setono_sylius_meilisearch.form.indexable.facetable_help',
                'required' => false,
            ])
            ->add('facetPosition', IntegerType::class, [
                'label' => 'setono_sylius_meilisearch.form.indexable.facet_position',
                'help' => 'setono_sylius_meilisearch.form.indexable.facet_position_help',
                'required' => false,
                'empty_data' => '0',
            ])
            ->add('indexes', IndexChoiceType::class, [
                'multiple' => true,
                'expanded' => true,
                'label' => 'setono_sylius_meilisearch.form.indexable.indexes',
                'dynamic_fields_only' => true,
            ])
            ->add('enabled', CheckboxType::class, [
                'label' => 'sylius.ui.enabled',
                'required' => false,
            ])
        ;
    }

    /**
     * @return array<string, string> a map of [choice label => code]
     */
    abstract protected function getCodeChoices(): array;

    abstract protected function getCodeLabel(): string;
}
