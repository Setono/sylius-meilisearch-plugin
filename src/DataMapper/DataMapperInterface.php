<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\DataMapper;

use Setono\SyliusMeilisearchPlugin\Document\Document;
use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScope;

interface DataMapperInterface
{
    /**
     * Maps $source to $target. This means properties on $target are updated, but properties on $source remain untouched
     *
     * @param array<string, mixed> $context
     */
    public function map(IndexableInterface $source, Document $target, IndexScope $indexScope, array $context = []): void;

    /**
     * Returns true if this data mapper supports the given $source and $target
     *
     * @param array<string, mixed> $context
     */
    public function supports(IndexableInterface $source, Document $target, IndexScope $indexScope, array $context = []): bool;
}
