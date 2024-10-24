<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\DataMapper\Product;

use PHPUnit\Framework\TestCase;
use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\DataMapper\Product\TaxonsDataMapper;
use Setono\SyliusMeilisearchPlugin\Document\Product as ProductDocument;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScope;
use Setono\SyliusMeilisearchPlugin\Tests\Application\Entity\Product;
use Sylius\Component\Core\Model\Taxon;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class TaxonsDataMapperTest extends TestCase
{
    /**
     * @test
     */
    public function it_maps(): void
    {
        $mainTaxon = new Taxon();
        $mainTaxon->setCurrentLocale('en_US');
        $mainTaxon->setName('level5');

        $level4 = new Taxon();
        $level4->setCurrentLocale('en_US');
        $level4->setName('level4');
        $mainTaxon->setParent($level4);

        $level3 = new Taxon();
        $level3->setCurrentLocale('en_US');
        $level3->setName('level3');
        $level4->setParent($level3);

        $level2 = new Taxon();
        $level2->setCurrentLocale('en_US');
        $level2->setName('level2');
        $level3->setParent($level2);

        $level1 = new Taxon();
        $level1->setCurrentLocale('en_US');
        $level1->setName('level1');
        $level2->setParent($level1);

        $level0 = new Taxon();
        $level0->setCurrentLocale('en_US');
        $level0->setName('level0');
        $level1->setParent($level0);

        $product = new Product();
        $product->setMainTaxon($mainTaxon);

        $document = new ProductDocument();
        $indexScope = new IndexScope(new Index('products', ProductDocument::class, [Product::class], new ContainerBuilder()), null, 'en_US');

        $dataMapper = new TaxonsDataMapper(levelsToInclude: 3, topLevelsToExclude: 1);
        $dataMapper->map($product, $document, $indexScope);

        self::assertSame(['level1 > level2 > level3'], $document->taxons);
    }
}
