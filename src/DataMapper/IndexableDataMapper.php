<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\DataMapper;

use Setono\SyliusMeilisearchPlugin\Document\Document;
use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScope;

final class IndexableDataMapper implements DataMapperInterface
{
    public function map(IndexableInterface $source, Document $target, IndexScope $indexScope, array $context = []): void
    {
        $target->id = $source->getDocumentIdentifier();
        $target->entityId = (string) $source->getId();
        $target->entityClass = $source::class;
    }

    public function supports(IndexableInterface $source, Document $target, IndexScope $indexScope, array $context = []): bool
    {
        return true;
    }
}
