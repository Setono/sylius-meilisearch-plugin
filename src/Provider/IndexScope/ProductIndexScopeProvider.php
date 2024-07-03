<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Provider\IndexScope;

use Setono\SyliusMeilisearchPlugin\Config\Index;
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
        return new IndexScope(
            index: $index,
            channelCode: $this->channelContext->getChannel()->getCode(),
            localeCode: $this->localeContext->getLocaleCode(),
            currencyCode: $this->currencyContext->getCurrencyCode(),
        );
    }

    public function supports(Index $index): bool
    {
        return count($index->entities) === 1 && $index->hasEntity(ProductInterface::class);
    }
}
