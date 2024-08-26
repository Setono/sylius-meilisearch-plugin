<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin;

use Setono\CompositeCompilerPass\CompositeCompilerPass;
use Setono\SyliusMeilisearchPlugin\DataMapper\CompositeDataMapper;
use Setono\SyliusMeilisearchPlugin\Form\Builder\CompositeFacetFormBuilder;
use Sylius\Bundle\CoreBundle\Application\SyliusPluginTrait;
use Sylius\Bundle\ResourceBundle\AbstractResourceBundle;
use Sylius\Bundle\ResourceBundle\SyliusResourceBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class SetonoSyliusMeilisearchPlugin extends AbstractResourceBundle
{
    use SyliusPluginTrait;

    public function getSupportedDrivers(): array
    {
        return [
            SyliusResourceBundle::DRIVER_DOCTRINE_ORM,
        ];
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // Register services in composite services
        $container->addCompilerPass(new CompositeCompilerPass(
            CompositeFacetFormBuilder::class,
            'setono_sylius_meilisearch.facet_form_builder',
        ));

        $container->addCompilerPass(new CompositeCompilerPass(
            CompositeDataMapper::class,
            'setono_sylius_meilisearch.data_mapper',
        ));

        $container->addCompilerPass(new CompositeCompilerPass(
            'setono_sylius_meilisearch.url_generator.composite',
            'setono_sylius_meilisearch.url_generator',
        ));

        $container->addCompilerPass(new CompositeCompilerPass(
            'setono_sylius_meilisearch.provider.index_scope.composite',
            'setono_sylius_meilisearch.index_scope_provider',
        ));

        $container->addCompilerPass(new CompositeCompilerPass(
            'setono_sylius_meilisearch.filter.object.composite',
            'setono_sylius_meilisearch.object_filter',
        ));
    }
}
