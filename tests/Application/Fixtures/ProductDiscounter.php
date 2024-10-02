<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Application\Fixtures;

use Doctrine\Persistence\ManagerRegistry;
use Setono\Doctrine\ORMTrait;
use Sylius\Bundle\FixturesBundle\Listener\AbstractListener;
use Sylius\Bundle\FixturesBundle\Listener\AfterSuiteListenerInterface;
use Sylius\Bundle\FixturesBundle\Listener\SuiteEvent;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

final class ProductDiscounter extends AbstractListener implements AfterSuiteListenerInterface
{
    use ORMTrait;

    public function __construct(
        ManagerRegistry $managerRegistry,
        /**
         * @var class-string<ProductInterface>
         */
        private readonly string $productClass,
    ) {
        $this->managerRegistry = $managerRegistry;
    }

    // I tried to think of something more ugly, but I couldn't
    public function afterSuite(SuiteEvent $suiteEvent, array $options): void
    {
        $repository = $this->getRepository($this->productClass);

        $products = $repository->findAll();
        shuffle($products);

        $products = array_slice($products, 0, $options['amount']);

        /** @var ProductInterface $product */
        foreach ($products as $product) {
            /** @var ProductVariantInterface $variant */
            foreach ($product->getVariants() as $variant) {
                foreach ($variant->getChannelPricings() as $channelPricing) {
                    $channelPricing->setOriginalPrice($channelPricing->getPrice());
                    $channelPricing->setPrice((int) floor($channelPricing->getPrice() * (1 - $options['discount'])));
                }
            }
        }

        $this->getManager($this->productClass)->flush();
    }

    public function getName(): string
    {
        return 'product_discounter';
    }

    protected function configureOptionsNode(ArrayNodeDefinition $optionsNode): void
    {
        $optionsNode
            ->addDefaultsIfNotSet()
            ->children()
                ->integerNode('amount')
                    ->defaultValue(5)
                ->end()
                ->floatNode('discount')
                    ->defaultValue(0.1)
                    ->info('10% percent discount')
        ;
    }
}
