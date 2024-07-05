<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\DataMapper\Product;

use Setono\SyliusMeilisearchPlugin\DataMapper\DataMapperInterface;
use Setono\SyliusMeilisearchPlugin\Document\Document;
use Setono\SyliusMeilisearchPlugin\Document\Product as ProductDocument;
use function Setono\SyliusMeilisearchPlugin\formatAmount;
use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScope;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Currency\Converter\CurrencyConverterInterface;
use Webmozart\Assert\Assert;

/**
 * This data mapper maps prices on product documents
 */
final class PriceDataMapper implements DataMapperInterface
{
    public function __construct(
        private readonly ChannelRepositoryInterface $channelRepository,
        private readonly CurrencyConverterInterface $currencyConverter,
    ) {
    }

    /**
     * @param ProductDocument|Document $target
     * @param array<string, mixed> $context
     */
    public function map(IndexableInterface $source, Document $target, IndexScope $indexScope, array $context = []): void
    {
        Assert::true($this->supports($source, $target, $indexScope, $context));

        $channel = $this->channelRepository->findOneByCode($indexScope->channelCode);
        Assert::isInstanceOf($channel, ChannelInterface::class);

        $baseCurrencyCode = $this->getBaseCurrencyCode($channel);

        $price = null;
        $originalPrice = null;

        /**
         * Let's get the lowest price of any enabled variant and use that as our product price reference
         *
         * @var ProductVariantInterface $variant
         */
        foreach ($source->getEnabledVariants() as $variant) {
            $channelPricing = $variant->getChannelPricingForChannel($channel);
            if (null === $channelPricing) {
                continue;
            }

            if (null === $price || $channelPricing->getPrice() < $price) {
                $price = $channelPricing->getPrice();
                $originalPrice = $channelPricing->getOriginalPrice();
            }
        }

        // no variants have prices
        if (null === $price) {
            return;
        }

        $target->currency = $indexScope->currencyCode;
        $target->price = formatAmount($this->currencyConverter->convert($price, $baseCurrencyCode, $indexScope->currencyCode));

        if (null !== $originalPrice) {
            $target->originalPrice = formatAmount($originalPrice);
        }
    }

    /**
     * @psalm-assert-if-true ProductInterface $source
     * @psalm-assert-if-true ProductDocument $target
     * @psalm-assert-if-true !null $indexScope->channelCode
     * @psalm-assert-if-true !null $indexScope->currencyCode
     */
    public function supports(IndexableInterface $source, Document $target, IndexScope $indexScope, array $context = []): bool
    {
        return $source instanceof ProductInterface && $target instanceof ProductDocument && $indexScope->channelCode !== null;
    }

    private function getBaseCurrencyCode(ChannelInterface $channel): string
    {
        $baseCurrency = $channel->getBaseCurrency();
        Assert::notNull($baseCurrency);

        $baseCurrencyCode = $baseCurrency->getCode();
        Assert::notNull($baseCurrencyCode);

        return $baseCurrencyCode;
    }
}
