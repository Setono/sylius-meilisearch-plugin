<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Provider\IndexScope;

use Setono\SyliusMeilisearchPlugin\Config\Index;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;

final class DefaultIndexScopeProvider implements IndexScopeProviderInterface
{
    public function __construct(
        private readonly ChannelRepositoryInterface $channelRepository,
        private readonly ChannelContextInterface $channelContext,
        private readonly LocaleContextInterface $localeContext,
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
                yield new IndexScope(
                    index: $index,
                    channelCode: $channel->getCode(),
                    localeCode: $locale->getCode(),
                );
            }
        }
    }

    public function getFromContext(Index $index): IndexScope
    {
        return new IndexScope(
            index: $index,
            channelCode: $this->channelContext->getChannel()->getCode(),
            localeCode: $this->localeContext->getLocaleCode(),
        );
    }

    public function supports(Index $index): bool
    {
        return true;
    }
}
