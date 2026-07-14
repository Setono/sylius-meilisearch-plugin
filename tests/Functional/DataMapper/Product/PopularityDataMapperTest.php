<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Functional\DataMapper\Product;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
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

        // Reload the product from the database so it is a fully-hydrated managed entity, exactly
        // as it is when the indexer maps it in production. Constructing entities in memory and
        // flushing does not reliably back-populate generated ids on every supported Doctrine ORM
        // version (e.g. 2.14), which would leave the variant id null and skew the query.
        $product = $this->reloadProductByCode('popularity-test-product');

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

    public function testItMemoizesPopularityAcrossScopes(): void
    {
        /** @var ChannelRepositoryInterface $channelRepository */
        $channelRepository = self::getContainer()->get('sylius.repository.channel');
        /** @var ChannelInterface $channel */
        $channel = $channelRepository->findOneByCode('FASHION_WEB');

        $product = new Product();
        $product->setCode('popularity-memoization-product');
        $product->setEnabled(true);

        $variant = new ProductVariant();
        $variant->setCode('popularity-memoization-variant');
        $variant->setEnabled(true);
        $variant->setPosition(0);
        $product->addVariant($variant);

        $this->manager->persist($product);
        $this->manager->persist($variant);

        $this->createOrder($channel, $variant, 1, OrderPaymentStates::STATE_PAID);
        $this->manager->flush();

        // See the note in testItCountsDistinctPaidOrdersAndIgnoresQuantity: reload so the product
        // is a managed entity with populated ids, mirroring how the indexer maps it in production.
        $product = $this->reloadProductByCode('popularity-memoization-product');

        // Use a dedicated mapper instance so this white-box assertion on the private memoization
        // cache is isolated: the container-shared mapper is also invoked by the Doctrine flush
        // listener when it reindexes the flushed product, which would leave extra cache entries.
        /** @var ManagerRegistry $managerRegistry */
        $managerRegistry = self::getContainer()->get('doctrine');
        /** @var class-string $orderClass */
        $orderClass = self::getContainer()->getParameter('sylius.model.order.class');
        /** @var class-string $orderItemClass */
        $orderItemClass = self::getContainer()->getParameter('sylius.model.order_item.class');
        $dataMapper = new PopularityDataMapper($managerRegistry, $orderClass, $orderItemClass);

        /** @var Index $index */
        $index = self::getContainer()->get('setono_sylius_meilisearch.index.products');

        // Popularity does not vary by scope, so mapping the same product for two currency scopes
        // must produce the same value and only compute it once (memoized per product object).
        $document1 = new ProductDocument();
        $dataMapper->map($product, $document1, new IndexScope($index, 'FASHION_WEB', 'en_US', 'USD'));

        $document2 = new ProductDocument();
        $dataMapper->map($product, $document2, new IndexScope($index, 'FASHION_WEB', 'en_US', 'EUR'));

        self::assertSame(1.0, $document1->popularity);
        self::assertSame($document1->popularity, $document2->popularity);

        $cacheProperty = new \ReflectionProperty(PopularityDataMapper::class, 'popularityCache');
        $cache = $cacheProperty->getValue($dataMapper);
        self::assertInstanceOf(\Countable::class, $cache);
        self::assertCount(1, $cache, 'The popularity must be computed and cached once per product, not once per scope');
    }

    private function reloadProductByCode(string $code): Product
    {
        // Detach the in-memory graph so the lookup hydrates a fresh managed entity from the
        // database (with generated ids) rather than returning the identity-mapped instance.
        $this->manager->clear();

        $product = $this->manager->getRepository(Product::class)->findOneBy(['code' => $code]);
        self::assertInstanceOf(Product::class, $product);

        return $product;
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
