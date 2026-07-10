<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Resolver\Indexable;

use Setono\CompositeCompilerPass\CompositeService;

/**
 * @extends CompositeService<IndexableEntityResolverInterface>
 */
final class CompositeIndexableEntityResolver extends CompositeService implements IndexableEntityResolverInterface
{
    public function resolve(object $entity): iterable
    {
        foreach ($this->services as $service) {
            yield from $service->resolve($entity);
        }
    }
}
