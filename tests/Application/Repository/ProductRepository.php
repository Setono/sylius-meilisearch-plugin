<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Application\Repository;

use Setono\SyliusMeilisearchPlugin\Repository\IndexableResourceRepositoryInterface;
use Setono\SyliusMeilisearchPlugin\Repository\ProductRepositoryTrait;
use Sylius\Bundle\CoreBundle\Doctrine\ORM\ProductRepository as BaseProductRepository;

class ProductRepository extends BaseProductRepository implements IndexableResourceRepositoryInterface
{
    use ProductRepositoryTrait;
}
