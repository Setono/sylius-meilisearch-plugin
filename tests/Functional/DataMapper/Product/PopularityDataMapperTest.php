<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Functional\DataMapper\Product;

use Doctrine\ORM\EntityManagerInterface;
use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\DataMapper\Product\PopularityDataMapper;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScope;
use Setono\SyliusMeilisearchPlugin\Tests\Application\Document\Product as ProductDocument;
use Setono\SyliusMeilisearchPlugin\Tests\Application\Entity\Product;
use Setono\SyliusMeilisearchPlugin\Tests\Functional\FunctionalTestCase;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\Order;
use Sylius\Component\Core\Model\OrderItem;
use Sylius\Component\Core\Model\ProductVariant;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Core\OrderPaymentStates;

final class PopularityDataMapperTest extends FunctionalTestCase
{
    private EntityManagerInterface $manager;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var EntityManagerInterface $manager */
        $manager = self::getContainer()->get('doctrine.orm.entity_manager');
        $this->manager = $manager;

        // Isolate the test: use savepoints so the nested transaction Doctrine opens
        // during flush() nests under ours, then roll everything back in tearDown().
        $connection = $this->manager->getConnection();
        $connection->setNestTransactionsWithSavepoints(true);
        $connection->beginTransaction();
    }

    protected function tearDown(): void
    {
        $connection = $this->manager->getConnection();
        if ($connection->isTransactionActive()) {
            $connection->rollBack();
        }

        parent::tearDown();
    }

    public function testItCountsDistinctPaidOrdersAndIgnoresQuantity(): void
    {
        /** @var ChannelRepositoryInterface $channelRepository */
        $channelRepository = self::getContainer()->get('sylius.repository.channel');
        /** @var ChannelInterface $channel */
        $channel = $channelRepository->findOneByCode('FASHION_WEB');

        // A brand-new product/variant nothing else references, so the only orders that
        // count towards its popularity are the ones created below.
        $product = new Product();
        $product->setCode('popularity-test-product');
        $product->setEnabled(true);

        $variant = new ProductVariant();
        $variant->setCode('popularity-test-variant');
        $variant->setEnabled(true);
        $variant->setPosition(0);
        $product->addVariant($variant);

        $this->manager->persist($product);
        $this->manager->persist($variant);

        // One paid order that adds the product 200 times: under the old SUM(quantity)
        // logic this alone contributed 200, skewing the metric (see issue #39). It must
        // now count as a single appearance.
        $this->createOrder($channel, $variant, 200, OrderPaymentStates::STATE_PAID);

        // Three more paid orders, one unit each.
        $this->createOrder($channel, $variant, 1, OrderPaymentStates::STATE_PAID);
        $this->createOrder($channel, $variant, 1, OrderPaymentStates::STATE_PAID);
        $this->createOrder($channel, $variant, 1, OrderPaymentStates::STATE_PAID);

        // An unpaid order that must be excluded entirely.
        $this->createOrder($channel, $variant, 1, OrderPaymentStates::STATE_CART);

        $this->manager->flush();

        /** @var PopularityDataMapper $dataMapper */
        $dataMapper = self::getContainer()->get(PopularityDataMapper::class);

        /** @var Index $index */
        $index = self::getContainer()->get('setono_sylius_meilisearch.index.products');
        $indexScope = new IndexScope($index, 'FASHION_WEB', 'en_US', 'USD');

        $document = new ProductDocument();
        $dataMapper->map($product, $document, $indexScope);

        // 4 distinct paid orders. The 200-quantity order counts once (not 200) and the
        // unpaid order is excluded, so the old SUM(quantity) implementation would have
        // produced 203 here.
        self::assertSame(4.0, $document->popularity);
    }

    private function createOrder(
        ChannelInterface $channel,
        ProductVariantInterface $variant,
        int $quantity,
        string $paymentState,
    ): void {
        $order = new Order();
        $order->setChannel($channel);
        $order->setCurrencyCode('USD');
        $order->setLocaleCode('en_US');
        $order->setPaymentState($paymentState);

        $item = new OrderItem();
        $item->setVariant($variant);
        $item->setUnitPrice(1000);
        $this->setQuantity($item, $quantity);
        $order->addItem($item);

        $this->manager->persist($order);
        $this->manager->persist($item);
    }

    /**
     * The Sylius order item quantity is derived from its units and has no public setter,
     * so we set the mapped column directly — this test only cares about the persisted value.
     */
    private function setQuantity(OrderItem $item, int $quantity): void
    {
        $property = new \ReflectionProperty(\Sylius\Component\Order\Model\OrderItem::class, 'quantity');
        $property->setValue($item, $quantity);
    }
}
