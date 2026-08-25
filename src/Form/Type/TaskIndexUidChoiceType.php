<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Form\Type;

use Setono\SyliusMeilisearchPlugin\Provider\IndexUids\IndexUidsProviderInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class TaskIndexUidChoiceType extends AbstractType
{
    public function __construct(private readonly IndexUidsProviderInterface $indexUidsProvider)
    {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'choices' => function (Options $options): array {
                try {
                    $uids = $this->indexUidsProvider->getFlattened();
                } catch (\Throwable) {
                    // enumerating index scopes queries the database, and the filter form should not break if that fails
                    return [];
                }

                return array_combine($uids, $uids);
            },
        ]);
    }

    public function getParent(): string
    {
        return ChoiceType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'setono_sylius_meilisearch_task_index_uid_choice';
    }
}
