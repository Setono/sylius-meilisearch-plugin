<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\UrlGenerator;

use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;

interface EntityUrlGeneratorInterface
{
    /**
     * @param array<string, mixed> $context
     */
    public function generate(IndexableInterface $entity, array $context = []): string;

    /**
     * @param array<string, mixed> $context
     */
    public function supports(IndexableInterface $entity, array $context = []): bool;
}
