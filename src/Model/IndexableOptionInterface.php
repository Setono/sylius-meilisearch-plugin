<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Model;

use Sylius\Component\Product\Model\ProductOptionInterface;

/**
 * Configures a Sylius product option to be indexed in Meilisearch
 */
interface IndexableOptionInterface extends IndexableSubjectInterface
{
    public function getOption(): ?ProductOptionInterface;

    public function setOption(?ProductOptionInterface $option): void;
}
