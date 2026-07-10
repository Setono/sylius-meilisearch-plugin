<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\DependencyInjection;

use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Setono\SyliusMeilisearchPlugin\Controller\Action\SearchAction;
use Setono\SyliusMeilisearchPlugin\DependencyInjection\SetonoSyliusMeilisearchExtension;
use Setono\SyliusMeilisearchPlugin\Document\Product as ProductDocument;
use Setono\SyliusMeilisearchPlugin\Tests\Application\Entity\Product as ProductEntity;

/**
 * See examples of tests and configuration options here: https://github.com/SymfonyTest/SymfonyDependencyInjectionTest
 */
final class SetonoSyliusMeilisearchExtensionTest extends AbstractExtensionTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->container->setParameter('kernel.debug', true);
    }

    protected function getContainerExtensions(): array
    {
        return [
            new SetonoSyliusMeilisearchExtension(),
        ];
    }

    /**
     * @test
     */
    public function it_loads_without_any_configuration(): void
    {
        $this->load();

        $this->assertContainerBuilderHasParameter('setono_sylius_meilisearch.search.enabled', false);
        $this->assertContainerBuilderNotHasService(SearchAction::class);
    }

    /**
     * @test
     */
    public function it_falls_back_public_url_to_server_url_when_not_configured(): void
    {
        $this->load();

        $this->assertContainerBuilderHasParameter('setono_sylius_meilisearch.server.public_url', 'http://localhost:7700');
    }

    /**
     * @test
     */
    public function it_falls_back_public_url_to_server_url_when_empty(): void
    {
        $this->load([
            'server' => [
                'public_url' => '',
            ],
        ]);

        $this->assertContainerBuilderHasParameter('setono_sylius_meilisearch.server.public_url', 'http://localhost:7700');
    }

    /**
     * @test
     */
    public function it_uses_and_normalizes_the_configured_public_url(): void
    {
        $this->load([
            'server' => [
                // protocol-relative to also assert the scheme is coerced to http, just like the server url
                'public_url' => '//search.example.com',
            ],
        ]);

        $this->assertContainerBuilderHasParameter('setono_sylius_meilisearch.server.public_url', 'http://search.example.com');
        // The server-side url is untouched by the public url
        $this->assertContainerBuilderHasParameter('setono_sylius_meilisearch.server.url', 'http://localhost:7700');
    }

    /**
     * @test
     */
    public function it_throws_exception_if_search_index_is_not_set(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->load([
            'search' => [
                'path' => 'search',
            ],
        ]);
    }

    /**
     * @test
     */
    public function it_throws_exception_if_search_index_is_not_configured(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->load([
            'search' => [
                'path' => 'search',
                'index' => 'products',
            ],
        ]);
    }

    /**
     * @test
     */
    public function it_configures_search(): void
    {
        $this->load([
            'indexes' => [
                'products' => [
                    'document' => ProductDocument::class,
                    'entities' => [ProductEntity::class],
                ],
            ],
            'search' => [
                'path' => 'search',
                'index' => 'products',
                'hits_per_page' => 120,
            ],
        ]);

        $this->assertContainerBuilderHasParameter('setono_sylius_meilisearch.search.path', 'search');
        $this->assertContainerBuilderHasParameter('setono_sylius_meilisearch.search.index', 'products');
        $this->assertContainerBuilderHasParameter('setono_sylius_meilisearch.search.hits_per_page', 120);
        $this->assertContainerBuilderHasParameter('setono_sylius_meilisearch.search.enabled', true);
        $this->assertContainerBuilderHasService(SearchAction::class);
    }

    /**
     * @test
     */
    public function it_configures_autocomplete_with_default_limit(): void
    {
        $this->load([
            'indexes' => [
                'products' => [
                    'document' => ProductDocument::class,
                    'entities' => [ProductEntity::class],
                ],
            ],
            'autocomplete' => [
                'enabled' => true,
                'indexes' => ['products'],
            ],
        ]);

        $this->assertContainerBuilderHasParameter('setono_sylius_meilisearch.autocomplete.limit', 5);
    }

    /**
     * @test
     */
    public function it_configures_a_custom_autocomplete_limit(): void
    {
        $this->load([
            'indexes' => [
                'products' => [
                    'document' => ProductDocument::class,
                    'entities' => [ProductEntity::class],
                ],
            ],
            'autocomplete' => [
                'enabled' => true,
                'indexes' => ['products'],
                'limit' => 8,
            ],
        ]);

        $this->assertContainerBuilderHasParameter('setono_sylius_meilisearch.autocomplete.limit', 8);
    }
}
