<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\DataMapper;

use Setono\SyliusMeilisearchPlugin\Config\IndexRegistry;
use Setono\SyliusMeilisearchPlugin\Document\Document;
use Setono\SyliusMeilisearchPlugin\IndexScope\IndexScope;
use Sylius\Component\Resource\Model\ResourceInterface;

final class ResourceNameDataMapper implements DataMapperInterface
{
    private IndexRegistry $indexRegistry;

    public function __construct(IndexRegistry $indexRegistry)
    {
        $this->indexRegistry = $indexRegistry;
    }

    public function map(ResourceInterface $source, Document $target, IndexScope $indexScope, array $context = []): void
    {
        foreach ($this->indexRegistry as $index) {
            foreach ($index->resources as $resource) {
                if ($resource->is($source)) {
                    $target->resourceName = $resource->name;

                    return;
                }
            }
        }
    }

    public function supports(
        ResourceInterface $source,
        Document $target,
        IndexScope $indexScope,
        array $context = []
    ): bool {
        return true;
    }
}
