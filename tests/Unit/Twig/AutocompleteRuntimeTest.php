<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\Twig;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusMeilisearchPlugin\Meilisearch\Autocomplete\SourceResolverInterface;
use Setono\SyliusMeilisearchPlugin\Twig\AutocompleteRuntime;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @covers \Setono\SyliusMeilisearchPlugin\Twig\AutocompleteRuntime
 */
final class AutocompleteRuntimeTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_builds_the_configuration(): void
    {
        $translator = $this->prophesize(TranslatorInterface::class);
        $translator->trans('placeholder')->willReturn('Search...');

        $urlGenerator = $this->prophesize(UrlGeneratorInterface::class);
        $urlGenerator->generate('setono_sylius_meilisearch_shop_search')->willReturn('/search');

        $runtime = new AutocompleteRuntime(
            $this->prophesize(SourceResolverInterface::class)->reveal(),
            $translator->reveal(),
            $urlGenerator->reveal(),
            'http://localhost:7700',
            'search-only-key',
            '#autocomplete',
            'placeholder',
            [],
            false,
        );

        $configuration = $runtime->configuration();

        self::assertStringContainsString('ssm-autocomplete-configuration', $configuration);
        self::assertStringContainsString('search-only-key', $configuration);
        self::assertStringContainsString('Search...', $configuration);
    }
}
