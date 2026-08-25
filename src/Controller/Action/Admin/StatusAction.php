<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Controller\Action\Admin;

use Setono\SyliusMeilisearchPlugin\Meilisearch\Server\ServerInformationProviderInterface;
use Sylius\Component\Grid\Parameters;
use Sylius\Component\Grid\Provider\GridProviderInterface;
use Sylius\Component\Grid\View\GridViewFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

final class StatusAction
{
    public function __construct(
        private readonly Environment $twig,
        private readonly GridProviderInterface $gridProvider,
        private readonly GridViewFactoryInterface $gridViewFactory,
        private readonly ServerInformationProviderInterface $serverInformationProvider,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $grid = $this->gridProvider->get('setono_sylius_meilisearch_admin_task');
        $gridView = $this->gridViewFactory->create($grid, new Parameters($request->query->all()));

        return new Response($this->twig->render('@SetonoSyliusMeilisearchPlugin/admin/status/index.html.twig', [
            'grid_view' => $gridView,
            'server_information' => $this->serverInformationProvider->get(),
        ]));
    }
}
