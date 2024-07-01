<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Provider\IndexScope;

use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\IndexScope\IndexScope;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Currency\Context\CurrencyContextInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;

final class ProductIndexScopeProvider implements IndexScopeProviderInterface
{
    public function __construct(
        private readonly ChannelContextInterface $channelContext,
        private readonly LocaleContextInterface $localeContext,
        private readonly CurrencyContextInterface $currencyContext,
        private readonly ChannelRepositoryInterface $channelRepository,
    ) {
    }

    public function getAll(Index $index): iterable
    {
        /** @var ChannelInterface[] $channels */
        $channels = $this->channelRepository->findBy([
            'enabled' => true,
        ]);

        foreach ($channels as $channel) {
            foreach ($channel->getLocales() as $locale) {
                foreach ($channel->getCurrencies() as $currency) {
                    yield new IndexScope(
                        index: $index,
                        channelCode: $channel->getCode(),
                        localeCode: $locale->getCode(),
                        currencyCode: $currency->getCode(),
                    );
                }
            }
        }
    }

    public function getFromContext(Index $index): IndexScope
    {
        return $this->getFromChannelAndLocaleAndCurrency(
            $index,
            $this->channelContext->getChannel()->getCode(),
            $this->localeContext->getLocaleCode(),
            $this->currencyContext->getCurrencyCode(),
        );
    }

    public function getFromChannelAndLocaleAndCurrency(
        Index $index,
        string $channelCode = null,
        string $localeCode = null,
        string $currencyCode = null,
    ): IndexScope {
        return new IndexScope(
            index: $index,
            channelCode: $channelCode,
            localeCode: $localeCode,
            currencyCode: $currencyCode,
        );
    }

    public function supports(Index $index): bool
    {
        return $index->hasResourceWithClass(ProductInterface::class);
    }
}
