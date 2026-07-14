<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\DataMapper\Product;

use Doctrine\ORM\NoResultException;
use Doctrine\Persistence\ManagerRegistry;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusMeilisearchPlugin\DataMapper\DataMapperInterface;
use Setono\SyliusMeilisearchPlugin\Document\Document;
use Setono\SyliusMeilisearchPlugin\Document\Product;
use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScope;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\OrderPaymentStates;
use Sylius\Component\Product\Model\ProductVariantInterface;
use Webmozart\Assert\Assert;

final class PopularityDataMapper implements DataMapperInterface
{
    use ORMTrait;

    /**
     * Popularity does not vary by scope (channel/locale/currency), but map() is called once per
     * scope for every entity in a batch. Memoize the computed value per product object so the
     * COUNT query runs once per product rather than once per product × scope. Keying on the object
     * (via a WeakMap) means the cache is bounded and drops entries automatically once a batch's
     * entities are detached by EntityManager::clear() and garbage collected.
     *
     * @var \WeakMap<object, int>
     */
    private \WeakMap $popularityCache;

    public function __construct(
        ManagerRegistry $managerRegistry,
        /** @var class-string $orderClass */
        private readonly string $orderClass,
        /** @var class-string $orderItemClass */
        private readonly string $orderItemClass,
        private readonly string $popularityLookBackPeriod = '3 months',
    ) {
        $this->managerRegistry = $managerRegistry;
        $this->popularityCache = new \WeakMap();
    }

    /**
     * @param Product|Document $target
     * @param array<string, mixed> $context
     */
    public function map(IndexableInterface $source, Document $target, IndexScope $indexScope, array $context = []): void
    {
        Assert::true(
            $this->supports($source, $target, $indexScope, $context),
            'The given $source and $target is not supported',
        );

        if (!isset($this->popularityCache[$source])) {
            $this->popularityCache[$source] = $this->calculatePopularity($source);
        }

        $target->popularity = $this->popularityCache[$source];
    }

    private function calculatePopularity(ProductInterface $source): int
    {
        $variants = $source->getEnabledVariants()->map(static fn (ProductVariantInterface $variant): int => (int) $variant->getId())->toArray();
        if ([] === $variants) {
            return 0;
        }

        $orderIdLowerBound = $this->getOrderIdLowerBound();

        // Count the number of distinct *paid* orders in which one of the product's
        // enabled variants appears. We count distinct orders (not SUM(quantity)) so a
        // single large-quantity order does not skew popularity — see issue #39.
        $qb = $this->getManager($this->orderItemClass)
            ->createQueryBuilder()
            ->select('COUNT(DISTINCT IDENTITY(o.order))')
            ->from($this->orderItemClass, 'o')
            ->innerJoin('o.order', 'ord')
            ->andWhere('ord.id >= :orderIdLowerBound')
            ->andWhere('ord.paymentState = :paymentState')
            ->andWhere('o.variant IN (:variants)')
            ->setParameter('orderIdLowerBound', $orderIdLowerBound)
            ->setParameter('paymentState', OrderPaymentStates::STATE_PAID)
            ->setParameter('variants', $variants)
        ;

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @psalm-assert-if-true ProductInterface $source
     * @psalm-assert-if-true Product $target
     */
    public function supports(
        IndexableInterface $source,
        Document $target,
        IndexScope $indexScope,
        array $context = [],
    ): bool {
        return $source instanceof ProductInterface && $target instanceof Product;
    }

    private function getOrderIdLowerBound(): int
    {
        try {
            return (int) $this->getManager($this->orderClass)
                ->createQueryBuilder()
                ->select('o.id')
                ->from($this->orderClass, 'o')
                ->andWhere('o.createdAt >= :date')
                ->setMaxResults(1)
                ->addOrderBy('o.id', 'ASC')
                ->setParameter('date', new \DateTimeImmutable('-' . $this->popularityLookBackPeriod))
                ->getQuery()
                ->enableResultCache(3600) // Notice that we cache the result for 1 hour to avoid making this same query for every entity
                ->getSingleScalarResult();
        } catch (NoResultException) {
            return 0;
        }
    }
}
