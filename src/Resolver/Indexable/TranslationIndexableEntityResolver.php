<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Resolver\Indexable;

use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
use Sylius\Component\Resource\Model\TranslationInterface;

/**
 * A change to a translation resolves to the translatable entity it belongs to.
 */
final class TranslationIndexableEntityResolver implements IndexableEntityResolverInterface
{
    public function resolve(object $entity): iterable
    {
        if (!$entity instanceof TranslationInterface) {
            return;
        }

        $translatable = $entity->getTranslatable();
        if ($translatable instanceof IndexableInterface) {
            yield $translatable;
        }
    }
}
