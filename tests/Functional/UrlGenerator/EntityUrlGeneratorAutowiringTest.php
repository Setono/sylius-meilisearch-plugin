<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Functional\UrlGenerator;

use Setono\SyliusMeilisearchPlugin\UrlGenerator\CompositeEntityUrlGenerator;
use Setono\SyliusMeilisearchPlugin\UrlGenerator\EntityUrlGeneratorInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * @covers \Setono\SyliusMeilisearchPlugin\UrlGenerator\CompositeEntityUrlGenerator
 */
final class EntityUrlGeneratorAutowiringTest extends KernelTestCase
{
    /**
     * The alias id for EntityUrlGeneratorInterface must not carry a leading backslash,
     * otherwise autowiring (which resolves type-hints to the FQCN without a leading
     * backslash) never matches the alias.
     *
     * @test
     */
    public function it_wires_the_interface_to_the_composite_generator(): void
    {
        self::bootKernel(['environment' => 'test', 'debug' => true]);

        $container = self::getContainer();

        self::assertTrue($container->has(EntityUrlGeneratorInterface::class));
        self::assertInstanceOf(
            CompositeEntityUrlGenerator::class,
            $container->get(EntityUrlGeneratorInterface::class),
        );
    }
}
