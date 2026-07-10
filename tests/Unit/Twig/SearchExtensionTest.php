<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\Twig;

use PHPUnit\Framework\TestCase;
use Setono\SyliusMeilisearchPlugin\Twig\AutocompleteExtension;
use Setono\SyliusMeilisearchPlugin\Twig\SearchExtension;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

/**
 * @covers \Setono\SyliusMeilisearchPlugin\Twig\SearchExtension
 */
final class SearchExtensionTest extends TestCase
{
    /**
     * @test
     */
    public function it_renders_the_autocomplete_container_when_autocomplete_is_enabled(): void
    {
        self::assertStringContainsString('<div id="autocomplete">', self::render(searchEnabled: true, autocompleteEnabled: true));
    }

    /**
     * @test
     */
    public function it_renders_the_search_form_when_only_search_is_enabled(): void
    {
        self::assertStringContainsString('[fragment: /setono_sylius_meilisearch_shop_search_widget]', self::render(searchEnabled: true, autocompleteEnabled: false));
    }

    /**
     * @test
     */
    public function it_renders_nothing_when_search_and_autocomplete_are_disabled(): void
    {
        self::assertSame('', trim(self::render(searchEnabled: false, autocompleteEnabled: false)));
    }

    private static function render(bool $searchEnabled, bool $autocompleteEnabled): string
    {
        $pluginLoader = new FilesystemLoader();
        $pluginLoader->addPath(__DIR__ . '/../../../src/Resources/views', 'SetonoSyliusMeilisearchPlugin');

        $twig = new Environment(new ChainLoader([
            new ArrayLoader(['test.html.twig' => '{{ ssm_search_widget() }}']),
            $pluginLoader,
        ]));
        $twig->addExtension(new SearchExtension($searchEnabled));
        $twig->addExtension(new AutocompleteExtension($autocompleteEnabled));

        // stubs for the functions normally provided by symfony/twig-bridge
        $twig->addFunction(new TwigFunction('path', static fn (string $name): string => '/' . $name));
        $twig->addFunction(new TwigFunction('render', static fn (string $uri): string => sprintf('[fragment: %s]', $uri), ['is_safe' => ['html']]));

        return $twig->render('test.html.twig');
    }
}
