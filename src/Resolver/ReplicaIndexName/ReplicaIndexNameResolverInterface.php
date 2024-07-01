<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Resolver\ReplicaIndexName;

interface ReplicaIndexNameResolverInterface
{
    public function resolveFromIndexNameAndExistingValue(string $indexName, string $existingValue): string;

    public function resolveFromIndexNameAndSortableAttribute(string $indexName, string $attribute, string $order): string;
}
