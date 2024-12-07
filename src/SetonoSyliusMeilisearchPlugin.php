<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin;

use Setono\CompositeCompilerPass\CompositeCompilerPass;
use Setono\SyliusMeilisearchPlugin\DataMapper\CompositeDataMapper;
use Setono\SyliusMeilisearchPlugin\Filter\Entity\CompositeEntityFilter;
use Setono\SyliusMeilisearchPlugin\Form\Builder\CompositeFilterFormBuilder;
use Setono\SyliusMeilisearchPlugin\Meilisearch\Filter\CompositeFilterBuilder;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\CompositeIndexScopeProvider;
use Setono\SyliusMeilisearchPlugin\Provider\Settings\CompositeSettingsProvider;
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
            CompositeFilterFormBuilder::class,
            'setono_sylius_meilisearch.filter_form_builder',
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
            CompositeIndexScopeProvider::class,
            'setono_sylius_meilisearch.index_scope_provider',
        ));

        $container->addCompilerPass(new CompositeCompilerPass(
            CompositeSettingsProvider::class,
            'setono_sylius_meilisearch.settings_provider',
        ));

        $container->addCompilerPass(new CompositeCompilerPass(
            CompositeEntityFilter::class,
            'setono_sylius_meilisearch.entity_filter',
        ));

        $container->addCompilerPass(new CompositeCompilerPass(
            CompositeFilterBuilder::class,
            'setono_sylius_meilisearch.filter_builder',
        ));
    }
}
