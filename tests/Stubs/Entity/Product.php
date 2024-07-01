<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Stubs\Entity;

use Setono\SyliusMeilisearchPlugin\Model\IndexableAwareTrait;
use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
use Sylius\Component\Core\Model\Product as BaseProduct;

class Product extends BaseProduct implements IndexableInterface
{
    use IndexableAwareTrait;

    public function getId(): ?int
    {
        return (int) $this->id;
    }
}
