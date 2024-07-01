<?php
declare(strict_types=1);


namespace Setono\SyliusMeilisearchPlugin\Tests\Application\Document;


use Setono\SyliusMeilisearchPlugin\Document\Product as BaseProduct;

final class Product extends BaseProduct
{
    public static function getSortableAttributes(): array
    {
        return [
            'price' => 'asc',
        ];
    }
}
