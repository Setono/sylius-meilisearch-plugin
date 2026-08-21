<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\DependencyInjection;

use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Setono\SyliusMeilisearchPlugin\Controller\Action\SearchAction;
use Setono\SyliusMeilisearchPlugin\DependencyInjection\SetonoSyliusMeilisearchExtension;
use Setono\SyliusMeilisearchPlugin\Document\Product as ProductDocument;
use Setono\SyliusMeilisearchPlugin\EventSubscriber\Search\ReplaceTaxonControllerSubscriber;
use Setono\SyliusMeilisearchPlugin\EventSubscriber\Search\TaxonSearchSubscriber;
use Setono\SyliusMeilisearchPlugin\Model\IndexableAttribute;
use Setono\SyliusMeilisearchPlugin\Model\IndexableOption;
use Setono\SyliusMeilisearchPlugin\Tests\Application\Entity\Product as ProductEntity;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

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
        $this->assertContainerBuilderNotHasService(ReplaceTaxonControllerSubscriber::class);
        $this->assertContainerBuilderNotHasService(TaxonSearchSubscriber::class);
    }

    /**
     * @test
     */
    public function it_registers_the_indexable_attribute_and_option_resources(): void
    {
        $this->load();

        $this->assertContainerBuilderHasParameter(
            'setono_sylius_meilisearch.model.indexable_attribute.class',
            IndexableAttribute::class,
        );
        $this->assertContainerBuilderHasService('setono_sylius_meilisearch.repository.indexable_attribute');

        $this->assertContainerBuilderHasParameter(
            'setono_sylius_meilisearch.model.indexable_option.class',
            IndexableOption::class,
        );
        $this->assertContainerBuilderHasService('setono_sylius_meilisearch.repository.indexable_option');
    }

    /**
     * @test
     */
    public function it_passes_the_dynamic_fields_flag_to_the_index(): void
    {
        $this->load([
            'indexes' => [
                'products' => [
                    'document' => ProductDocument::class,
                    'entities' => [ProductEntity::class],
                    'dynamic_fields' => false,
                ],
            ],
        ]);

        $this->assertContainerBuilderHasServiceDefinitionWithArgument('setono_sylius_meilisearch.index.products', 5, false);
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
    public function it_throws_when_an_unknown_default_filter_key_is_used(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        // The message lists the valid keys
        $this->expectExceptionMessageMatches('/stock_available/');

        $this->load([
            'indexes' => [
                'products' => [
                    'document' => ProductDocument::class,
                    'entities' => [ProductEntity::class],
                    'default_filters' => [
                        'stock_availabe' => true, // typo on purpose
                    ],
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function it_throws_when_using_the_reserved_search_index_name(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/reserved/');

        $this->load([
            'indexes' => [
                'search' => [
                    'document' => ProductDocument::class,
                    'entities' => [ProductEntity::class],
                ],
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

        // The taxon takeover is enabled by default when search is enabled
        $this->assertContainerBuilderHasParameter('setono_sylius_meilisearch.search.taxon.enabled', true);
        $this->assertContainerBuilderHasService(ReplaceTaxonControllerSubscriber::class);
        $this->assertContainerBuilderHasService(TaxonSearchSubscriber::class);
    }

    /**
     * @test
     */
    public function it_disables_the_taxon_takeover(): void
    {
        $this->load([
            'indexes' => [
                'products' => [
                    'document' => ProductDocument::class,
                    'entities' => [ProductEntity::class],
                ],
            ],
            'search' => [
                'index' => 'products',
                'taxon' => [
                    'enabled' => false,
                ],
            ],
        ]);

        $this->assertContainerBuilderHasParameter('setono_sylius_meilisearch.search.taxon.enabled', false);
        $this->assertContainerBuilderHasService(SearchAction::class);
        $this->assertContainerBuilderNotHasService(ReplaceTaxonControllerSubscriber::class);
        $this->assertContainerBuilderNotHasService(TaxonSearchSubscriber::class);
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

    /**
     * @test
     */
    public function it_throws_when_the_search_key_equals_the_master_key_and_autocomplete_is_enabled(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/master key/');

        $this->load([
            'server' => [
                'search_key' => 'the-same-key',
                'master_key' => 'the-same-key',
            ],
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
    }

    /**
     * @test
     */
    public function it_allows_equal_search_and_master_keys_when_autocomplete_is_disabled(): void
    {
        // The key is only exposed to the browser through autocomplete, so the guard does not apply here
        $this->load([
            'server' => [
                'search_key' => 'the-same-key',
                'master_key' => 'the-same-key',
            ],
        ]);

        $this->assertContainerBuilderHasParameter('setono_sylius_meilisearch.autocomplete.enabled', false);
    }

    /**
     * @test
     */
    public function it_allows_distinct_search_and_master_keys_with_autocomplete_enabled(): void
    {
        $this->load([
            'server' => [
                'search_key' => 'search-only-key',
                'master_key' => 'master-key',
            ],
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

        $this->assertContainerBuilderHasParameter('setono_sylius_meilisearch.autocomplete.enabled', true);
    }
}
