<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Functional\Document\Metadata;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Setono\SyliusMeilisearchPlugin\Config\IndexRegistryInterface;
use Setono\SyliusMeilisearchPlugin\Model\IndexableAttribute;
use Setono\SyliusMeilisearchPlugin\Model\IndexableOption;
use Setono\SyliusMeilisearchPlugin\Model\IndexableSubject;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScope;
use Setono\SyliusMeilisearchPlugin\Provider\Settings\SettingsProviderInterface;
use Sylius\Component\Product\Model\ProductAttributeInterface;
use Sylius\Component\Product\Model\ProductOptionInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * End to end test of the DB driven metadata: persisted IndexableAttribute/IndexableOption rows for the
 * test app's fixture attributes must end up in the resolved metadata of the products index (and in the
 * settings derived from it), while the taxons index stays untouched.
 *
 * @covers \Setono\SyliusMeilisearchPlugin\EventSubscriber\IndexableSubjectMetadataSubscriber
 */
final class DynamicFieldsMetadataTest extends KernelTestCase
{
    /**
     * @test
     */
    public function it_merges_admin_configured_attributes_into_the_products_index_metadata(): void
    {
        self::bootKernel(['environment' => 'test', 'debug' => true]);
        $container = self::getContainer();

        /** @var ManagerRegistry $registry */
        $registry = $container->get('doctrine');

        /** @var ObjectManager $manager */
        $manager = $registry->getManagerForClass(IndexableAttribute::class);

        /** @var RepositoryInterface<ProductAttributeInterface> $attributeRepository */
        $attributeRepository = $container->get('sylius.repository.product_attribute');

        /** @var RepositoryInterface<ProductOptionInterface> $optionRepository */
        $optionRepository = $container->get('sylius.repository.product_option');

        // these attributes/options are defined in the test app's fixtures (see _sylius.yaml):
        // color is a multi select, eco_friendly a checkbox and production_date a date
        $rows = [
            self::configure(self::attributeRow($attributeRepository, 'color'), facetable: true),
            self::configure(self::attributeRow($attributeRepository, 'eco_friendly'), facetable: true),
            self::configure(self::attributeRow($attributeRepository, 'production_date'), searchable: true),
            self::configure(self::optionRow($optionRepository, 'dress_size'), facetable: true),
        ];

        foreach ($rows as $row) {
            $manager->persist($row);
        }
        $manager->flush();

        try {
            /** @var IndexRegistryInterface $indexRegistry */
            $indexRegistry = $container->get('setono_sylius_meilisearch.config.index_registry');

            $metadata = $indexRegistry->get('products')->metadata();

            self::assertArrayHasKey('attr_color', $metadata->facetableAttributes);
            self::assertSame('array', $metadata->facetableAttributes['attr_color']->type);
            self::assertArrayHasKey('attr_eco_friendly', $metadata->facetableAttributes);
            self::assertSame('bool', $metadata->facetableAttributes['attr_eco_friendly']->type);
            self::assertArrayHasKey('opt_dress_size', $metadata->facetableAttributes);
            self::assertSame('array', $metadata->facetableAttributes['opt_dress_size']->type);

            // a facetable field must also be filterable
            self::assertContains('attr_color', $metadata->getFilterableAttributeNames());
            self::assertContains('attr_eco_friendly', $metadata->getFilterableAttributeNames());
            self::assertContains('opt_dress_size', $metadata->getFilterableAttributeNames());

            // a date attribute is searchable but never a facet
            self::assertContains('attr_production_date', $metadata->getSearchableAttributeNames());
            self::assertArrayNotHasKey('attr_production_date', $metadata->facetableAttributes);

            // the settings pushed to Meilisearch include the dynamic fields
            /** @var SettingsProviderInterface $settingsProvider */
            $settingsProvider = $container->get(SettingsProviderInterface::class);
            $settings = $settingsProvider->getSettings(new IndexScope($indexRegistry->get('products')));

            self::assertContains('attr_color', $settings->filterableAttributes->jsonSerialize());
            self::assertContains('attr_production_date', $settings->searchableAttributes->jsonSerialize());

            // the taxons index does not carry product attributes
            self::assertSame([], $indexRegistry->get('taxons')->metadata()->dynamicFields);
        } finally {
            foreach ($rows as $row) {
                $manager->remove($row);
            }
            $manager->flush();
        }
    }

    /**
     * @param RepositoryInterface<ProductAttributeInterface> $repository
     */
    private static function attributeRow(RepositoryInterface $repository, string $code): IndexableAttribute
    {
        $attribute = $repository->findOneBy(['code' => $code]);
        self::assertInstanceOf(ProductAttributeInterface::class, $attribute);

        $row = new IndexableAttribute();
        $row->setAttribute($attribute);

        return $row;
    }

    /**
     * @param RepositoryInterface<ProductOptionInterface> $repository
     */
    private static function optionRow(RepositoryInterface $repository, string $code): IndexableOption
    {
        $option = $repository->findOneBy(['code' => $code]);
        self::assertInstanceOf(ProductOptionInterface::class, $option);

        $row = new IndexableOption();
        $row->setOption($option);

        return $row;
    }

    /**
     * @template T of IndexableSubject
     *
     * @param T $row
     *
     * @return T
     */
    private static function configure(
        IndexableSubject $row,
        bool $searchable = false,
        bool $facetable = false,
    ): IndexableSubject {
        $row->setSearchable($searchable);
        $row->setFacetable($facetable);
        $row->addIndex('products');
        $row->setEnabled(true);

        return $row;
    }
}
