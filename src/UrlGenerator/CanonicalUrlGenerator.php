<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\UrlGenerator;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class CanonicalUrlGenerator implements CanonicalUrlGeneratorInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function generate(Request $request, array $parameters = []): string
    {
        /** @var string|null $routeName */
        $routeName = $request->attributes->get('_route');

        if (null === $routeName) {
            return $request->getUri();
        }

        /** @var array<string, string> $routeParams */
        $routeParams = $request->attributes->get('_route_params', []);

        return $this->urlGenerator->generate(
            $routeName,
            array_merge($routeParams, $parameters),
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
    }
}
