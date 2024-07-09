<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\DependencyInjection;

use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Setono\SyliusMeilisearchPlugin\Controller\SearchController;
use Setono\SyliusMeilisearchPlugin\DependencyInjection\SetonoSyliusMeilisearchExtension;
use Setono\SyliusMeilisearchPlugin\Document\Product as ProductDocument;
use Setono\SyliusMeilisearchPlugin\Tests\Application\Entity\Product as ProductEntity;

/**
 * See examples of tests and configuration options here: https://github.com/SymfonyTest/SymfonyDependencyInjectionTest
 */
final class SetonoSyliusMeilisearchExtensionTest extends AbstractExtensionTestCase
{
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
        $this->assertContainerBuilderNotHasService(SearchController::class);
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
        $this->assertContainerBuilderHasService(SearchController::class);
    }
}
