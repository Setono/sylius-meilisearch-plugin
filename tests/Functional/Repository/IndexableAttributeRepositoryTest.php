<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Functional\Repository;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Setono\SyliusMeilisearchPlugin\Model\IndexableAttribute;
use Setono\SyliusMeilisearchPlugin\Model\IndexableAttributeInterface;
use Setono\SyliusMeilisearchPlugin\Repository\IndexableAttributeRepositoryInterface;
use Sylius\Component\Product\Model\ProductAttributeInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * @covers \Setono\SyliusMeilisearchPlugin\Repository\IndexableAttributeRepository
 */
final class IndexableAttributeRepositoryTest extends KernelTestCase
{
    /**
     * The `indexes` column is a JSON list and the lookup uses a quote-anchored LIKE
     * (`%"products"%`), so a row registered for `products_v2` must not be returned when
     * looking up rows for `products`. Disabled rows must not be returned either.
     *
     * @test
     */
    public function it_finds_enabled_rows_for_the_exact_index(): void
    {
        self::bootKernel(['environment' => 'test', 'debug' => true]);
        $container = self::getContainer();

        /** @var ManagerRegistry $registry */
        $registry = $container->get('doctrine');

        /** @var ObjectManager $manager */
        $manager = $registry->getManagerForClass(IndexableAttribute::class);

        /** @var RepositoryInterface<ProductAttributeInterface> $productAttributeRepository */
        $productAttributeRepository = $container->get('sylius.repository.product_attribute');

        // these product attributes are defined in the test app's fixtures
        $productsRow = self::createRow(self::findAttribute($productAttributeRepository, 'color'), ['products']);
        $productsV2Row = self::createRow(self::findAttribute($productAttributeRepository, 'eco_friendly'), ['products_v2']);
        $disabledRow = self::createRow(self::findAttribute($productAttributeRepository, 'production_date'), ['products'], enabled: false);

        $manager->persist($productsRow);
        $manager->persist($productsV2Row);
        $manager->persist($disabledRow);
        $manager->flush();

        try {
            /** @var IndexableAttributeRepositoryInterface $repository */
            $repository = $container->get('setono_sylius_meilisearch.repository.indexable_attribute');

            $codes = array_map(
                static fn (IndexableAttributeInterface $row): ?string => $row->getCode(),
                array_values($repository->findEnabledByIndex('products')),
            );

            self::assertContains('color', $codes);
            self::assertNotContains('eco_friendly', $codes);
            self::assertNotContains('production_date', $codes);
        } finally {
            $manager->remove($productsRow);
            $manager->remove($productsV2Row);
            $manager->remove($disabledRow);
            $manager->flush();
        }
    }

    /**
     * @param RepositoryInterface<ProductAttributeInterface> $repository
     */
    private static function findAttribute(RepositoryInterface $repository, string $code): ProductAttributeInterface
    {
        $attribute = $repository->findOneBy(['code' => $code]);
        self::assertInstanceOf(ProductAttributeInterface::class, $attribute);

        return $attribute;
    }

    /**
     * @param list<string> $indexes
     */
    private static function createRow(ProductAttributeInterface $attribute, array $indexes, bool $enabled = true): IndexableAttribute
    {
        $row = new IndexableAttribute();
        $row->setAttribute($attribute);
        $row->setSearchable(true);
        foreach ($indexes as $index) {
            $row->addIndex($index);
        }
        $row->setEnabled($enabled);

        return $row;
    }
}
