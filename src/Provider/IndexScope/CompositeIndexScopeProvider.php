<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Provider\IndexScope;

use Setono\CompositeCompilerPass\CompositeService;
use Setono\SyliusMeilisearchPlugin\Config\Index;

/**
 * @extends CompositeService<IndexScopeProviderInterface>
 */
final class CompositeIndexScopeProvider extends CompositeService implements IndexScopeProviderInterface
{
    public function getAll(Index $index): iterable
    {
        foreach ($this->services as $service) {
            if ($service->supports($index)) {
                yield from $service->getAll($index);

                return;
            }
        }

        throw self::createException($index->name);
    }

    public function getFromContext(Index $index): IndexScope
    {
        foreach ($this->services as $service) {
            if ($service->supports($index)) {
                return $service->getFromContext($index);
            }
        }

        throw self::createException($index->name);
    }

    public function supports(Index $index): bool
    {
        foreach ($this->services as $service) {
            if ($service->supports($index)) {
                return true;
            }
        }

        return false;
    }

    private static function createException(string $indexName): \InvalidArgumentException
    {
        return new \InvalidArgumentException(sprintf('The index %s is not supported by any index scope providers', $indexName));
    }
}
