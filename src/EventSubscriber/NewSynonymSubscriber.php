<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\EventSubscriber;

use Doctrine\Persistence\ManagerRegistry;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusMeilisearchPlugin\Factory\SynonymFactoryInterface;
use Setono\SyliusMeilisearchPlugin\Model\SynonymInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\Exception\OutOfBoundsException;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Webmozart\Assert\Assert;

final class NewSynonymSubscriber implements EventSubscriberInterface
{
    use ORMTrait;

    public function __construct(ManagerRegistry $managerRegistry, private readonly SynonymFactoryInterface $synonymFactory)
    {
        $this->managerRegistry = $managerRegistry;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            FormEvents::POST_SUBMIT => 'handleFormEvent',
        ];
    }

    public function handleFormEvent(FormEvent $event): void
    {
        try {
            $direction = $event->getForm()->get('direction')->getData();
        } catch (OutOfBoundsException) {
            return;
        }

        // todo replace with constant
        if ('two_way' !== $direction) {
            return;
        }

        /** @var mixed $synonym */
        $synonym = $event->getData();
        Assert::isInstanceOf($synonym, SynonymInterface::class);

        $newSynonym = $this->synonymFactory->createInverseFromExisting($synonym);

        $this->getManager($newSynonym)->persist($newSynonym);
    }
}
