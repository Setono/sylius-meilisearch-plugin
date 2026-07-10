<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Functional\Repository;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Setono\SyliusMeilisearchPlugin\Config\IndexRegistryInterface;
use Setono\SyliusMeilisearchPlugin\Model\Synonym;
use Setono\SyliusMeilisearchPlugin\Model\SynonymInterface;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScope;
use Setono\SyliusMeilisearchPlugin\Repository\SynonymRepositoryInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * @covers \Setono\SyliusMeilisearchPlugin\Repository\SynonymRepository
 */
final class SynonymRepositoryTest extends KernelTestCase
{
    /**
     * The `indexes` column is a JSON list and the lookup uses a quote-anchored LIKE
     * (`%"products"%`). A synonym registered for `products_v2` must therefore not be
     * returned when looking up synonyms for `products`, even though "products" is a
     * substring of "products_v2".
     *
     * @test
     */
    public function it_does_not_match_an_index_name_that_is_a_prefix_of_another(): void
    {
        self::bootKernel(['environment' => 'test', 'debug' => true]);
        $container = self::getContainer();

        /** @var ManagerRegistry $registry */
        $registry = $container->get('doctrine');

        /** @var ObjectManager $manager */
        $manager = $registry->getManagerForClass(Synonym::class);

        /** @var RepositoryInterface<LocaleInterface> $localeRepository */
        $localeRepository = $container->get('sylius.repository.locale');
        $locale = $localeRepository->findOneBy([]);
        self::assertInstanceOf(LocaleInterface::class, $locale);

        $productsSynonym = $this->createSynonym('substring-safety-products', ['products'], $locale);
        $productsV2Synonym = $this->createSynonym('substring-safety-products-v2', ['products_v2'], $locale);

        $manager->persist($productsSynonym);
        $manager->persist($productsV2Synonym);
        $manager->flush();

        try {
            /** @var SynonymRepositoryInterface $repository */
            $repository = $container->get('setono_sylius_meilisearch.repository.synonym');

            /** @var IndexRegistryInterface $indexRegistry */
            $indexRegistry = $container->get('setono_sylius_meilisearch.config.index_registry');

            $indexScope = new IndexScope($indexRegistry->get('products'));

            $terms = array_map(
                static fn (SynonymInterface $synonym): ?string => $synonym->getTerm(),
                array_values($repository->findEnabledByIndexScope($indexScope)),
            );

            self::assertContains('substring-safety-products', $terms);
            self::assertNotContains('substring-safety-products-v2', $terms);
        } finally {
            $manager->remove($productsSynonym);
            $manager->remove($productsV2Synonym);
            $manager->flush();
        }
    }

    /**
     * @param list<string> $indexes
     */
    private function createSynonym(string $term, array $indexes, LocaleInterface $locale): Synonym
    {
        $synonym = new Synonym();
        $synonym->setTerm($term);
        $synonym->setSynonym($term . '-syn');
        $synonym->setLocale($locale);
        foreach ($indexes as $index) {
            $synonym->addIndex($index);
        }
        $synonym->enable();

        return $synonym;
    }
}
