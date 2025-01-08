<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Controller\Action;

use Setono\SyliusMeilisearchPlugin\Form\Type\SearchWidgetType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * Shows the global search form (not the autocomplete)
 */
final class SearchWidgetAction
{
    public function __construct(
        private readonly Environment $twig,
        private readonly FormFactoryInterface $formFactory,
    ) {
    }

    public function __invoke(): Response
    {
        return new Response($this->twig->render('@SetonoSyliusMeilisearchPlugin/search_widget/_content.html.twig', [
            'form' => $this->formFactory->createNamed('', SearchWidgetType::class)->createView(),
        ]));
    }
}
