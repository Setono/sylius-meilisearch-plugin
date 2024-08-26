<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\DataMapper\Product;

use Setono\SyliusMeilisearchPlugin\DataMapper\DataMapperInterface;
use Setono\SyliusMeilisearchPlugin\DataMapper\Product\Provider\ProductPricesProviderInterface;
use Setono\SyliusMeilisearchPlugin\Document\Document;
use Setono\SyliusMeilisearchPlugin\Document\Product as ProductDocument;
use function Setono\SyliusMeilisearchPlugin\formatAmount;
use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScope;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Currency\Converter\CurrencyConverterInterface;
use Webmozart\Assert\Assert;

final class PriceDataMapper implements DataMapperInterface
{
    public function __construct(
        private readonly ChannelRepositoryInterface $channelRepository,
        private readonly CurrencyConverterInterface $currencyConverter,
        private readonly ProductPricesProviderInterface $productPricesProvider,
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

        $prices = $this->productPricesProvider->getPricesForChannel($source, $channel);

        $target->currency = $indexScope->currencyCode;
        $target->price = $this->formatAndConvertAmount($prices->price, $baseCurrencyCode, $indexScope->currencyCode);
        $target->originalPrice = $this->formatAndConvertAmount($prices->originalPrice, $baseCurrencyCode, $indexScope->currencyCode);
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

    private function formatAndConvertAmount(?int $amount, string $sourceCurrencyCode, string $targetCurrencyCode): ?float
    {
        if (null === $amount) {
            return null;
        }

        return formatAmount($this->currencyConverter->convert($amount, $sourceCurrencyCode, $targetCurrencyCode));
    }
}
