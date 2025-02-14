<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Setono\SyliusMeilisearchPlugin\Engine\SearchEngine;
use Setono\SyliusMeilisearchPlugin\Engine\SearchRequest;
use Sylius\Component\Core\Model\ChannelPricingInterface;

final class SearchTest extends FunctionalTestCase
{
    public function testItProvidesSearchResults(): void
    {
        /** @var SearchEngine $searchEngine */
        $searchEngine = self::getContainer()->get(SearchEngine::class);
        $result = $searchEngine->execute(new SearchRequest('jeans'));

        self::assertSame(8, $result->totalHits);
    }

    public function testItProvidesSearchResultByMultipleCriteria(): void
    {
        $priceBounds = $this->getPriceBounds();

        /** @var SearchEngine $searchEngine */
        $searchEngine = self::getContainer()->get(SearchEngine::class);
        $result = $searchEngine->execute(
            new SearchRequest('jeans', [
                'brand' => ['Celsius small', 'You are breathtaking'],
                'price' => ['min' => $priceBounds[0], 'max' => $priceBounds[1]],
            ]),
        );

        foreach ($result->hits as $hit) {
            self::assertGreaterThanOrEqual($priceBounds[0], (int) $hit['price']);
            self::assertLessThanOrEqual($priceBounds[1], (int) $hit['price']);
            self::assertContains(((array) $hit['brand'])[0], ['Celsius small', 'You are breathtaking']);
        }
    }

    public function testItAlwaysDisplaysFullFacetDistribution(): void
    {
        /** @var SearchEngine $searchEngine */
        $searchEngine = self::getContainer()->get(SearchEngine::class);
        $result = $searchEngine->execute(
            new SearchRequest('jeans', [
                'brand' => ['Celsius small'],
            ]),
        );

        $this->assertSame(1, $result->totalHits);
        $this->assertCount(4, $result->facetDistribution['brand']);
    }

    /**
     * From all available prices in the database, this method will return a lower bound that is at least greater than the 10th cheapest price
     * and an upper bound that will include 10 prices starting from the lower bound.
     *
     * @return array{int, int}
     */
    private function getPriceBounds(): array
    {
        $container = self::getContainer();

        /** @var ManagerRegistry $managerRegistry */
        $managerRegistry = $container->get('doctrine');

        /** @var class-string<ChannelPricingInterface> $channelPricingClass */
        $channelPricingClass = $container->getParameter('sylius.model.channel_pricing.class');

        /** @var EntityManagerInterface $manager */
        $manager = $managerRegistry->getManagerForClass($channelPricingClass);

        /** @var array<array-key, int> $prices */
        $prices = array_map(
            static fn (array $row) => (int) $row['price'],
            $manager->createQueryBuilder()
                ->select('o.price')
                ->from($channelPricingClass, 'o')
                ->getQuery()
                ->getScalarResult(),
        );

        sort($prices);

        $lowerBound = random_int(10, count($prices) - 20);
        $upperBound = $lowerBound + 10;

        return [(int) floor($prices[$lowerBound] / 100), (int) ceil($prices[$upperBound] / 100)];
    }
}
