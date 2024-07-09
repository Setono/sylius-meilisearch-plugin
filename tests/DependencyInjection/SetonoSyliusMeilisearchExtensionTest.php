<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\DependencyInjection;

use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Setono\SyliusMeilisearchPlugin\DependencyInjection\SetonoSyliusMeilisearchExtension;

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
}
