<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Form\Type;

use Setono\SyliusMeilisearchPlugin\Model\IndexableOptionInterface;
use Sylius\Component\Product\Model\ProductOptionInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;

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

    protected function getCodeChoices(): array
    {
        $choices = [];
        foreach ($this->productOptionRepository->findAll() as $option) {
            $choices[sprintf('%s (%s)', (string) $option->getName(), (string) $option->getCode())] = (string) $option->getCode();
        }

        ksort($choices);

        return $choices;
    }

    protected function getCodeLabel(): string
    {
        return 'setono_sylius_meilisearch.form.indexable_option.option';
    }
}
