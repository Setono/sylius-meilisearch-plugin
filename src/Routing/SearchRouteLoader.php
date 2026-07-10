<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Routing;

use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\RouteCollection;

/**
 * Loads the search routes (routes/search.yaml) only when search is enabled. When search is disabled
 * the search controller services are not registered either (see services/conditional/search.xml), so
 * the routes would otherwise point at non-existent controllers.
 *
 * The gated routes live in a dedicated search.yaml — not the generic shop.yaml — so that unrelated
 * shop routes are never accidentally swept up by the search.enabled toggle.
 */
final class SearchRouteLoader extends Loader
{
    public function __construct(private readonly bool $enabled, ?string $env = null)
    {
        parent::__construct($env);
    }

    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        $routes = new RouteCollection();

        if ($this->enabled) {
            /** @var RouteCollection $imported */
            $imported = $this->import('@SetonoSyliusMeilisearchPlugin/Resources/config/routes/search.yaml');
            $routes->addCollection($imported);
        }

        return $routes;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return 'setono_sylius_meilisearch_search' === $type;
    }
}
