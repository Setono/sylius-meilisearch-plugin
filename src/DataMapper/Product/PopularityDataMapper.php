<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\DataMapper\Product;

use Doctrine\Persistence\ManagerRegistry;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusMeilisearchPlugin\DataMapper\DataMapperInterface;
use Setono\SyliusMeilisearchPlugin\Document\Document;
use Setono\SyliusMeilisearchPlugin\Document\Product;
use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScope;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Product\Model\ProductVariantInterface;
use Webmozart\Assert\Assert;

final class PopularityDataMapper implements DataMapperInterface
{
    use ORMTrait;

    public function __construct(
        ManagerRegistry $managerRegistry,
        /** @var class-string $orderClass */
        private readonly string $orderClass,
        /** @var class-string $orderItemClass */
        private readonly string $orderItemClass,
        private readonly string $popularityLookBackPeriod = '3 months',
    ) {
        $this->managerRegistry = $managerRegistry;
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

        $variants = $source->getEnabledVariants()->map(static fn (ProductVariantInterface $variant): int => (int) $variant->getId())->toArray();
        if ([] === $variants) {
            return;
        }

        $orderIdLowerBound = $this->getOrderIdLowerBound();

        // todo do we need some filters on the order? E.g. state...

        $qb = $this->getManager($this->orderItemClass)
            ->createQueryBuilder()
            ->select('SUM(o.quantity)')
            ->from($this->orderItemClass, 'o')
            ->andWhere('IDENTITY(o.order) >= :orderIdLowerBound')
            ->andWhere('o.variant IN (:variants)')
            ->setParameter('orderIdLowerBound', $orderIdLowerBound)
            ->setParameter('variants', $variants)
        ;

        // todo in the future this value should be normalized so that it will be easier for plugin users to add to the popularity score
        $target->popularity = (int) $qb->getQuery()->getSingleScalarResult();
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
        return (int) $this->getManager($this->orderClass)
            ->createQueryBuilder()
            ->select('o.id')
            ->from($this->orderClass, 'o')
            ->andwhere('o.createdAt >= :date')
            ->setMaxResults(1)
            ->addOrderBy('o.id', 'ASC')
            ->setParameter('date', new \DateTimeImmutable('-' . $this->popularityLookBackPeriod))
            ->getQuery()
            ->enableResultCache(3600) // todo should this be cached and should it be configurable?
            ->getSingleScalarResult()
        ;
    }
}
