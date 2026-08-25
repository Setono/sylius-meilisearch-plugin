<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Form\Type;

use Setono\SyliusMeilisearchPlugin\Model\IndexableOptionInterface;
use Sylius\Component\Product\Model\ProductOptionInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;

final class IndexableOptionType extends IndexableSubjectType
{
    /**
     * @param RepositoryInterface<ProductOptionInterface> $productOptionRepository
     * @param class-string<IndexableOptionInterface> $dataClass
     * @param list<string> $validationGroups
     */
    public function __construct(
        private readonly RepositoryInterface $productOptionRepository,
        string $dataClass,
        array $validationGroups = [],
    ) {
        parent::__construct($dataClass, $validationGroups);
    }

    protected function addSubjectField(FormBuilderInterface $builder): void
    {
        $choices = $this->productOptionRepository->findAll();
        usort($choices, static fn (ProductOptionInterface $a, ProductOptionInterface $b): int => (string) $a->getName() <=> (string) $b->getName());

        $builder->add('option', ChoiceType::class, [
            'label' => 'setono_sylius_meilisearch.form.indexable_option.option',
            'choices' => $choices,
            'choice_value' => 'code',
            'choice_label' => 'name',
            'choice_translation_domain' => false,
        ]);
    }
}
