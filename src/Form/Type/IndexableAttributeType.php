<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Form\Type;

use Setono\SyliusMeilisearchPlugin\Model\IndexableAttributeInterface;
use Sylius\Component\Product\Model\ProductAttributeInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;

final class IndexableAttributeType extends IndexableSubjectType
{
    /**
     * @param RepositoryInterface<ProductAttributeInterface> $productAttributeRepository
     * @param class-string<IndexableAttributeInterface> $dataClass
     * @param list<string> $validationGroups
     */
    public function __construct(
        private readonly RepositoryInterface $productAttributeRepository,
        string $dataClass,
        array $validationGroups = [],
    ) {
        parent::__construct($dataClass, $validationGroups);
    }

    protected function addSubjectField(FormBuilderInterface $builder): void
    {
        $choices = $this->productAttributeRepository->findAll();
        usort($choices, static fn (ProductAttributeInterface $a, ProductAttributeInterface $b): int => (string) $a->getName() <=> (string) $b->getName());

        $builder->add('attribute', ChoiceType::class, [
            'label' => 'setono_sylius_meilisearch.form.indexable_attribute.attribute',
            'choices' => $choices,
            'choice_value' => 'code',
            'choice_label' => 'name',
            'choice_translation_domain' => false,
        ]);
    }
}
