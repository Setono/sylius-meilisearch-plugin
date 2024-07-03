<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\DataMapper;

use Setono\CompositeCompilerPass\CompositeService;
use Setono\SyliusMeilisearchPlugin\Document\Document;
use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScope;

/**
 * @extends CompositeService<DataMapperInterface>
 */
final class CompositeDataMapper extends CompositeService implements DataMapperInterface
{
    public function map(IndexableInterface $source, Document $target, IndexScope $indexScope, array $context = []): void
    {
        foreach ($this->services as $service) {
            if ($service->supports($source, $target, $indexScope, $context)) {
                $service->map($source, $target, $indexScope, $context);
            }
        }
    }

    public function supports(IndexableInterface $source, Document $target, IndexScope $indexScope, array $context = []): bool
    {
        foreach ($this->services as $service) {
            if ($service->supports($source, $target, $indexScope, $context)) {
                return true;
            }
        }

        return false;
    }
}
