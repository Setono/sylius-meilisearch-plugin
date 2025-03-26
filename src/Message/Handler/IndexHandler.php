<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Message\Handler;

use Meilisearch\Client;
use Setono\SyliusMeilisearchPlugin\Config\IndexRegistryInterface;
use Setono\SyliusMeilisearchPlugin\Message\Command\Index;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScopeProviderInterface;
use Setono\SyliusMeilisearchPlugin\Provider\Settings\SettingsProviderInterface;
use Setono\SyliusMeilisearchPlugin\Resolver\IndexUid\IndexUidResolverInterface;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class IndexHandler
{
    public function __construct(
        private readonly IndexRegistryInterface $indexRegistry,
        private readonly Client $client,
        private readonly SettingsProviderInterface $settingsProvider,
        private readonly IndexScopeProviderInterface $indexScopeProvider,
        private readonly IndexUidResolverInterface $indexUidResolver,
        private readonly NormalizerInterface $normalizer,
    ) {
    }

    public function __invoke(Index $message): void
    {
        try {
            $index = $this->indexRegistry->get($message->index);
        } catch (\InvalidArgumentException $e) {
            throw new UnrecoverableMessageHandlingException(message: $e->getMessage(), previous: $e);
        }

        foreach ($this->indexScopeProvider->getAll($index) as $indexScope) {
            $uid = $this->indexUidResolver->resolveFromIndexScope($indexScope);

            if ($message->delete) {
                $this->client->deleteIndex($uid);
            }

            $this
                ->client
                ->index($uid)
                ->updateSettings(
                    $this->normalizer->normalize($this->settingsProvider->getSettings($indexScope)),
                )
            ;
        }

        $index->indexer()->index();
    }
}
