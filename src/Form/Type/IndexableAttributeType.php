<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Form\Type;

use Setono\SyliusMeilisearchPlugin\Model\IndexableAttributeInterface;
use Sylius\Component\Product\Model\ProductAttributeInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;

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

    protected function getCodeChoices(): array
    {
        $choices = [];
        foreach ($this->productAttributeRepository->findAll() as $attribute) {
            $choices[sprintf('%s (%s)', (string) $attribute->getName(), (string) $attribute->getCode())] = (string) $attribute->getCode();
        }

        ksort($choices);

        return $choices;
    }

    protected function getCodeLabel(): string
    {
        return 'setono_sylius_meilisearch.form.indexable_attribute.attribute';
    }
}
