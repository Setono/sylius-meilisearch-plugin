<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\EventSubscriber\Search;

use Setono\SyliusMeilisearchPlugin\Controller\Action\SearchAction;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Serves Sylius' taxon page (the sylius_shop_product_index route) with the Meilisearch backed
 * search page by replacing the controller right after the router has matched the request.
 *
 * Taking over the existing route - instead of registering a competing route on the same path -
 * means every link Sylius generates for that route name (taxon menus, breadcrumbs, indexed
 * document urls) keeps working, regardless of the order the application imports its routing
 * files in, and changing the taxon page url is a single, standard override of the
 * sylius_shop_product_index route in the application.
 *
 * The subscriber runs at priority 31, immediately after Symfony's RouterListener (32), so every
 * later kernel.request listener already observes the final controller. The route name is the only
 * guard: fragment sub requests built from a ControllerReference carry a _controller but no _route
 * and are therefore left untouched. The replacement also applies when the application re-declares
 * the route with its own controller - disable it with setono_sylius_meilisearch.search.taxon.enabled.
 */
final class ReplaceTaxonControllerSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['replaceController', 31],
        ];
    }

    public function replaceController(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if ('sylius_shop_product_index' !== $request->attributes->get('_route')) {
            return;
        }

        $request->attributes->set('_controller', SearchAction::class);
    }
}
