<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Setono\SyliusMeilisearchPlugin\Engine\SearchEngine;
use Setono\SyliusMeilisearchPlugin\Engine\SearchRequest;
use Setono\SyliusMeilisearchPlugin\Message\Command\Index;
use Sylius\Component\Core\Model\ChannelPricingInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/** @group functional */
final class SearchSortingTest extends FunctionalTestCase
{
    public function testItSortsSearchResultsByLowestPrice(): void
    {
        /** @var SearchEngine $searchEngine */
        $searchEngine = self::getContainer()->get(SearchEngine::class);
        $result = $searchEngine->execute(new SearchRequest('jeans', sort: 'price:asc'));

        self::assertSame(8, $result->getHitsCount());

        $previousKey = null;
        foreach ($result->getHits() as $key => $hit) {
            if ($previousKey === null) {
                $previousKey = $key;

                continue;
            }

            $previousHit = (array) $result->getHit($previousKey);
            self::assertGreaterThanOrEqual($previousHit['price'], $hit['price']);
            $previousKey = $key;
        }
    }

    public function testItSortsSearchResultsByNewestDate(): void
    {
        /** @var SearchEngine $searchEngine */
        $searchEngine = self::getContainer()->get(SearchEngine::class);
        $result = $searchEngine->execute(new SearchRequest('jeans', sort: 'createdAt:desc'));

        self::assertSame(8, $result->getHitsCount());

        $previousKey = null;
        foreach ($result->getHits() as $key => $hit) {
            if ($previousKey === null) {
                $previousKey = $key;

                continue;
            }

            $previousHit = (array) $result->getHit($previousKey);
            self::assertLessThanOrEqual($previousHit['createdAt'], $hit['createdAt']);
            $previousKey = $key;
        }
    }

    public function testItSortsResultsByBiggestDiscount(): void
    {
        $this->changeProductPrice('990M_regular_fit_jeans', 10);

        /** @var SearchEngine $searchEngine */
        $searchEngine = self::getContainer()->get(SearchEngine::class);
        $result = $searchEngine->execute(new SearchRequest('jeans', sort: 'discount:desc'));

        /** @var array $firstHit */
        $firstHit = $result->getHit(0);
        self::assertSame(0.1, $firstHit['price']);
        self::assertGreaterThan(0, $firstHit['discount']);

        $this->resetProductPrices();
    }

    private function changeProductPrice(string $productCode, int $price): void
    {
        $channelPricing = $this->getChannelPricingOfProduct($productCode);
        $channelPricing->setOriginalPrice($channelPricing->getPrice());
        $channelPricing->setPrice($price);

        $this->saveAndReindexProduct();
    }

    private function resetProductPrices(): void
    {
        $channelPricing = $this->getChannelPricingOfProduct('990M_regular_fit_jeans');
        $channelPricing->setPrice($channelPricing->getOriginalPrice());
        $channelPricing->setOriginalPrice(null);

        $this->saveAndReindexProduct();
    }

    private function getChannelPricingOfProduct(string $code): ChannelPricingInterface
    {
        /** @var ProductRepositoryInterface $productRepository */
        $productRepository = self::getContainer()->get('sylius.repository.product');
        /** @var ProductInterface $product */
        $product = $productRepository->findOneBy(['code' => $code]);
        /** @var ProductVariantInterface $variant */
        $variant = $product->getVariants()->first();
        /** @var ChannelPricingInterface $channelPricing */
        $channelPricing = $variant->getChannelPricings()->first();

        return $channelPricing;
    }

    private function saveAndReindexProduct(): void
    {
        /** @var EntityManagerInterface $productManager */
        $productManager = self::getContainer()->get('sylius.manager.product');
        $productManager->flush();

        /** @var MessageBusInterface $commandBus */
        $commandBus = self::getContainer()->get('sylius.command_bus');
        $commandBus->dispatch(new Index('products'));

        // we need to wait for reindexing, at least a little bit :/
        sleep(1);
    }
}
