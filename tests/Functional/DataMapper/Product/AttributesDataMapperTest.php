<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Functional;

use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\DataMapper\Product\AttributesDataMapper;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScope;
use Setono\SyliusMeilisearchPlugin\Tests\Application\Document\Product as ProductDocument;
use Setono\SyliusMeilisearchPlugin\Tests\Application\Entity\Product;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;

/** @group functional */
final class AttributesDataMapperTest extends FunctionalTestCase
{
    public function testItMapsProductAttributesToDocumentProperties(): void
    {
        /** @var ProductRepositoryInterface $productRepository */
        $productRepository = self::getContainer()->get('sylius.repository.product');
        /** @var Product $jeansProduct */
        $jeansProduct = $productRepository->findOneBy(['code' => '990M_regular_fit_jeans']);

        /** @var AttributesDataMapper $dataMapper */
        $dataMapper = self::getContainer()->get(AttributesDataMapper::class);

        /** @var Index $index */
        $index = self::getContainer()->get('setono_sylius_meilisearch.index.products');
        $indexScope = new IndexScope($index, 'FASHION_WEB', 'en_US', 'USD');

        $document = new ProductDocument();
        $dataMapper->map($jeansProduct, $document, $indexScope);

        self::assertSame(['Celsius Small'], $document->brand);
    }
}
