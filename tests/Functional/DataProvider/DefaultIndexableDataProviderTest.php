<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Functional\DataProvider;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Setono\SyliusMeilisearchPlugin\Config\IndexRegistryInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * @covers \Setono\SyliusMeilisearchPlugin\DataProvider\DefaultIndexableDataProvider
 */
final class DefaultIndexableDataProviderTest extends KernelTestCase
{
    /**
     * containsId() must agree with getIds() (it is the single source of truth for both the batch and
     * incremental indexing paths), and it must honour the query-builder filters — disabling a product
     * (which the "enabled" subscriber filters on) has to make containsId() return false.
     *
     * @test
     */
    public function it_reflects_the_query_builder_filters_for_a_single_id(): void
    {
        self::bootKernel(['environment' => 'test', 'debug' => true]);
        $container = self::getContainer();

        /** @var IndexRegistryInterface $indexRegistry */
        $indexRegistry = $container->get('setono_sylius_meilisearch.config.index_registry');
        $index = $indexRegistry->get('products');

        $dataProvider = $index->dataProvider();
        $entityClass = $index->entities[0];

        $ids = iterator_to_array($dataProvider->getIds($entityClass, $index), false);
        self::assertNotEmpty($ids, 'Expected the fixtures to contain at least one indexable product');
        $id = (int) reset($ids);

        // getIds() yielded it, so containsId() must too — this is the invariant that keeps a full
        // reindex and an incremental update in agreement.
        self::assertTrue($dataProvider->containsId($entityClass, $index, $id));

        $manager = $this->getManager($container->get('doctrine'), $entityClass);

        // Disable the product straight in the database (a DQL bulk update bypasses the Doctrine lifecycle
        // listener, so this does not dispatch any indexing message) and assert the "enabled" query-builder
        // filter now excludes it from the membership check.
        $this->setEnabled($manager, $entityClass, $id, false);

        try {
            self::assertFalse($dataProvider->containsId($entityClass, $index, $id));
        } finally {
            $this->setEnabled($manager, $entityClass, $id, true);
        }
    }

    /**
     * @param class-string $entityClass
     */
    private function setEnabled(EntityManagerInterface $manager, string $entityClass, int $id, bool $enabled): void
    {
        $manager->createQuery(sprintf('UPDATE %s o SET o.enabled = :enabled WHERE o.id = :id', $entityClass))
            ->setParameter('enabled', $enabled)
            ->setParameter('id', $id)
            ->execute()
        ;
    }

    /**
     * @param class-string $entityClass
     */
    private function getManager(mixed $registry, string $entityClass): EntityManagerInterface
    {
        self::assertInstanceOf(ManagerRegistry::class, $registry);
        $manager = $registry->getManagerForClass($entityClass);
        self::assertInstanceOf(EntityManagerInterface::class, $manager);

        return $manager;
    }
}
