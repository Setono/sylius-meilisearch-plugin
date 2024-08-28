<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Filter\Entity;

use Setono\SyliusMeilisearchPlugin\Document\Document;
use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScope;
use Sylius\Component\Channel\Model\ChannelsAwareInterface;

final class ChannelsAwareEntityFilter implements EntityFilterInterface
{
    public function __construct(private readonly string $index)
    {
    }

    public function filter(IndexableInterface $entity, Document $document, IndexScope $indexScope): bool
    {
        if ($indexScope->index->name !== $this->index) {
            return true;
        }

        if (null === $indexScope->channelCode) {
            return true;
        }

        if (!$entity instanceof ChannelsAwareInterface) {
            return true;
        }

        foreach ($entity->getChannels() as $channel) {
            if ($indexScope->channelCode === $channel->getCode()) {
                return true;
            }
        }

        return false;
    }
}
