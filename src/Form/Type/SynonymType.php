<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Form\Type;

use Setono\SyliusMeilisearchPlugin\Model\SynonymInterface;
use Sylius\Bundle\ChannelBundle\Form\Type\ChannelChoiceType;
use Sylius\Bundle\LocaleBundle\Form\Type\LocaleChoiceType;
use Sylius\Bundle\ResourceBundle\Form\Type\AbstractResourceType;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Webmozart\Assert\Assert;

final class SynonymType extends AbstractResourceType
{
    /**
     * @param class-string<SynonymInterface> $dataClass
     * @param list<string> $validationGroups
     */
    public function __construct(
        private readonly EventSubscriberInterface $newSynonymSubscriber,
        string $dataClass,
        array $validationGroups = [],
    ) {
        parent::__construct($dataClass, $validationGroups);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('term', TextType::class, [
                'label' => 'setono_sylius_meilisearch.form.synonym.term',
            ])
            ->add('synonym', TextType::class, [
                'label' => 'setono_sylius_meilisearch.form.synonym.synonym',
            ])
            ->add('locale', LocaleChoiceType::class, [
                'label' => 'sylius.ui.locale',
            ])
            ->add('channels', ChannelChoiceType::class, [
                'multiple' => true,
                'expanded' => true,
                'label' => 'sylius.ui.channels',
                'required' => false,
            ])
            ->add('indexes', IndexChoiceType::class, [
                'multiple' => true,
                'expanded' => true,
                'label' => 'setono_sylius_meilisearch.form.synonym.indexes',
                'required' => false,
            ])
            ->add('enabled', CheckboxType::class, [
                'label' => 'sylius.ui.enabled',
                'required' => false,
            ])
            ->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
                /** @var mixed $synonym */
                $synonym = $event->getData();
                Assert::isInstanceOf($synonym, SynonymInterface::class);

                if ($synonym->getId() !== null) {
                    return;
                }

                $event->getForm()->add('direction', ChoiceType::class, [
                    'choices' => [
                        'setono_sylius_meilisearch.form.synonym.direction.one_way' => 'one_way',
                        'setono_sylius_meilisearch.form.synonym.direction.two_way' => 'two_way',
                    ],
                    'label' => 'setono_sylius_meilisearch.form.synonym.direction.label',
                    'mapped' => false,
                ]);
            })
            ->addEventSubscriber($this->newSynonymSubscriber)
        ;
    }
}
