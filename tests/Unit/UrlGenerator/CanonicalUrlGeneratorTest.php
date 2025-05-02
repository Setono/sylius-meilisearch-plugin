<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\UrlGenerator;

use PHPUnit\Framework\TestCase;
use Setono\SyliusMeilisearchPlugin\UrlGenerator\CanonicalUrlGenerator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class CanonicalUrlGeneratorTest extends TestCase
{
    public function testGenerate(): void
    {
        $routes = new RouteCollection();
        $routes->add('test_route', new Route('/test/{param1}'));
        $context = new RequestContext(host: 'example.com', scheme: 'https');
        $urlGenerator = new UrlGenerator($routes, $context);
        $canonicalUrlGenerator = new CanonicalUrlGenerator($urlGenerator);

        $request = Request::create('https://example.com/test/value1?param2=value2#fragment');
        $request->attributes->set('_route', 'test_route');
        $request->attributes->set('_route_params', ['param1' => 'value1']);

        $result = $canonicalUrlGenerator->generate($request);

        self::assertSame('https://example.com/test/value1', $result);
    }

    public function testGenerateWithCustomParams(): void
    {
        $routes = new RouteCollection();
        $routes->add('test_route', new Route('/test/{param1}'));
        $context = new RequestContext(host: 'example.com', scheme: 'https');
        $urlGenerator = new UrlGenerator($routes, $context);
        $canonicalUrlGenerator = new CanonicalUrlGenerator($urlGenerator);

        $request = Request::create('https://example.com/test/value1?param2=value2#fragment');
        $request->attributes->set('_route', 'test_route');
        $request->attributes->set('_route_params', ['param1' => 'value1']);

        $result = $canonicalUrlGenerator->generate($request, ['param1' => 'new_value1', 'param3' => 'value3']);

        self::assertSame('https://example.com/test/new_value1?param3=value3', $result);
    }
}
