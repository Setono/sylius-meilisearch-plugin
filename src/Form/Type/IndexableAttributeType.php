<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Form\Type;

use Setono\SyliusMeilisearchPlugin\Model\IndexableAttributeInterface;
use Sylius\Bundle\ResourceBundle\Form\Type\AbstractResourceType;
use Sylius\Component\Product\Model\ProductAttributeInterface;
use Sylius\Component\Product\Model\ProductOptionInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Contracts\Translation\TranslatorInterface;
use Webmozart\Assert\Assert;

final class IndexableAttributeType extends AbstractResourceType
{
    /**
     * @param RepositoryInterface<ProductAttributeInterface> $productAttributeRepository
     * @param RepositoryInterface<ProductOptionInterface> $productOptionRepository
     * @param class-string<IndexableAttributeInterface> $dataClass
     * @param list<string> $validationGroups
     */
    public function __construct(
        private readonly RepositoryInterface $productAttributeRepository,
        private readonly RepositoryInterface $productOptionRepository,
        private readonly TranslatorInterface $translator,
        string $dataClass,
        array $validationGroups = [],
    ) {
        parent::__construct($dataClass, $validationGroups);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('searchable', CheckboxType::class, [
                'label' => 'setono_sylius_meilisearch.form.indexable_attribute.searchable',
                'help' => 'setono_sylius_meilisearch.form.indexable_attribute.searchable_help',
                'required' => false,
            ])
            ->add('filterable', CheckboxType::class, [
                'label' => 'setono_sylius_meilisearch.form.indexable_attribute.filterable',
                'help' => 'setono_sylius_meilisearch.form.indexable_attribute.filterable_help',
                'required' => false,
            ])
            ->add('facetable', CheckboxType::class, [
                'label' => 'setono_sylius_meilisearch.form.indexable_attribute.facetable',
                'help' => 'setono_sylius_meilisearch.form.indexable_attribute.facetable_help',
                'required' => false,
            ])
            ->add('facetPosition', IntegerType::class, [
                'label' => 'setono_sylius_meilisearch.form.indexable_attribute.facet_position',
                'help' => 'setono_sylius_meilisearch.form.indexable_attribute.facet_position_help',
                'required' => false,
                'empty_data' => '0',
            ])
            ->add('indexes', IndexChoiceType::class, [
                'multiple' => true,
                'expanded' => true,
                'label' => 'setono_sylius_meilisearch.form.indexable_attribute.indexes',
            ])
            ->add('enabled', CheckboxType::class, [
                'label' => 'sylius.ui.enabled',
                'required' => false,
            ])
            ->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
                /** @var mixed $data */
                $data = $event->getData();
                Assert::isInstanceOf($data, IndexableAttributeInterface::class);

                $subject = null;
                if (null !== $data->getType() && null !== $data->getCode()) {
                    $subject = sprintf('%s|%s', $data->getType(), $data->getCode());
                }

                $event->getForm()->add('subject', ChoiceType::class, [
                    'choices' => $this->buildSubjectChoices(),
                    'label' => 'setono_sylius_meilisearch.form.indexable_attribute.subject',
                    'mapped' => false,
                    'data' => $subject,
                    // the subject identifies the row - delete and recreate to change it
                    'disabled' => $data->getId() !== null,
                    'choice_translation_domain' => false,
                ]);
            })
            ->addEventListener(FormEvents::SUBMIT, function (FormEvent $event): void {
                /** @var mixed $data */
                $data = $event->getData();
                Assert::isInstanceOf($data, IndexableAttributeInterface::class);

                $subjectForm = $event->getForm()->get('subject');
                if ($subjectForm->isDisabled()) {
                    return;
                }

                $subject = $subjectForm->getData();
                if (!is_string($subject) || !str_contains($subject, '|')) {
                    return;
                }

                [$type, $code] = explode('|', $subject, 2);

                $data->setType($type);
                $data->setCode($code);
            })
        ;
    }

    /**
     * @return array<string, array<string, string>> a map of [group label => [choice label => 'type|code']]
     */
    private function buildSubjectChoices(): array
    {
        $attributes = [];
        foreach ($this->productAttributeRepository->findAll() as $attribute) {
            $attributes[sprintf('%s (%s)', (string) $attribute->getName(), (string) $attribute->getCode())] = sprintf(
                '%s|%s',
                IndexableAttributeInterface::TYPE_ATTRIBUTE,
                (string) $attribute->getCode(),
            );
        }

        $options = [];
        foreach ($this->productOptionRepository->findAll() as $option) {
            $options[sprintf('%s (%s)', (string) $option->getName(), (string) $option->getCode())] = sprintf(
                '%s|%s',
                IndexableAttributeInterface::TYPE_OPTION,
                (string) $option->getCode(),
            );
        }

        ksort($attributes);
        ksort($options);

        return [
            $this->translator->trans('setono_sylius_meilisearch.form.indexable_attribute.subject_group.attributes') => $attributes,
            $this->translator->trans('setono_sylius_meilisearch.form.indexable_attribute.subject_group.options') => $options,
        ];
    }
}
