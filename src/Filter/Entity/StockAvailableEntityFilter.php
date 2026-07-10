<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Filter\Entity;

use Setono\SyliusMeilisearchPlugin\Document\Document;
use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScope;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;

/**
 * The object-filter counterpart of the "stock_available" query-builder subscriber. A product is
 * kept when it has at least one variant that is either untracked or has available stock
 * (onHand - onHold > 0); otherwise it is filtered out (and removed from the index on save).
 */
final class StockAvailableEntityFilter implements EntityFilterInterface
{
    public function __construct(private readonly string $index)
    {
    }

    public function filter(IndexableInterface $entity, Document $document, IndexScope $indexScope): bool
    {
        if ($indexScope->index->name !== $this->index) {
            return true;
        }

        if (!$entity instanceof ProductInterface) {
            return true;
        }

        foreach ($entity->getVariants() as $variant) {
            if (!$variant instanceof ProductVariantInterface) {
                continue;
            }

            if (!$variant->isTracked()) {
                return true;
            }

            if (((int) $variant->getOnHand() - (int) $variant->getOnHold()) > 0) {
                return true;
            }
        }

        return false;
    }
}
