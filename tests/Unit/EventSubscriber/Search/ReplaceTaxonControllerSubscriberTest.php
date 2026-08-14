<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\EventSubscriber\Search;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusMeilisearchPlugin\Controller\Action\SearchAction;
use Setono\SyliusMeilisearchPlugin\EventSubscriber\Search\ReplaceTaxonControllerSubscriber;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @covers \Setono\SyliusMeilisearchPlugin\EventSubscriber\Search\ReplaceTaxonControllerSubscriber
 */
final class ReplaceTaxonControllerSubscriberTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_replaces_the_controller_on_the_taxon_route(): void
    {
        $request = new Request(attributes: [
            '_route' => 'sylius_shop_product_index',
            '_controller' => 'sylius.controller.product::indexAction',
        ]);

        (new ReplaceTaxonControllerSubscriber())->replaceController($this->createEvent($request));

        self::assertSame(SearchAction::class, $request->attributes->get('_controller'));
    }

    /**
     * @test
     */
    public function it_ignores_other_routes(): void
    {
        $request = new Request(attributes: [
            '_route' => 'sylius_shop_product_show',
            '_controller' => 'sylius.controller.product::showAction',
        ]);

        (new ReplaceTaxonControllerSubscriber())->replaceController($this->createEvent($request));

        self::assertSame('sylius.controller.product::showAction', $request->attributes->get('_controller'));
    }

    /**
     * @test
     */
    public function it_ignores_requests_without_a_route(): void
    {
        // Fragment sub requests built from a ControllerReference carry a _controller but no _route
        $request = new Request(attributes: [
            '_controller' => 'sylius.controller.product::indexAction',
        ]);

        (new ReplaceTaxonControllerSubscriber())->replaceController($this->createEvent($request));

        self::assertSame('sylius.controller.product::indexAction', $request->attributes->get('_controller'));
    }

    /**
     * @test
     */
    public function it_runs_right_after_the_router(): void
    {
        // Symfony's RouterListener subscribes at priority 32; running at 31 makes the controller
        // replacement atomic with routing, so every later kernel.request listener sees the final controller
        self::assertSame(
            [KernelEvents::REQUEST => ['replaceController', 31]],
            ReplaceTaxonControllerSubscriber::getSubscribedEvents(),
        );
    }

    private function createEvent(Request $request): RequestEvent
    {
        return new RequestEvent(
            $this->prophesize(HttpKernelInterface::class)->reveal(),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );
    }
}
