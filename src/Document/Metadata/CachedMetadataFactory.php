<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Document\Metadata;

use Psr\Cache\CacheItemPoolInterface;
use Setono\SyliusMeilisearchPlugin\Document\Document;
use Webmozart\Assert\Assert;

final class CachedMetadataFactory implements MetadataFactoryInterface
{
    /**
     * The loaded metadata, indexed by class name
     *
     * @var array<class-string<Document>, Metadata>
     */
    private array $loadedClasses = [];

    public function __construct(
        private readonly MetadataFactoryInterface $baseMetadataFactory,
        private readonly CacheItemPoolInterface $cache,
    ) {
    }

    public function getMetadataFor(string|Document $document): Metadata
    {
        if ($document instanceof Document) {
            $document = $document::class;
        }

        if (isset($this->loadedClasses[$document])) {
            return $this->loadedClasses[$document];
        }

        $cacheItem = $this->cache->getItem($this->escapeClassName($document));
        if ($cacheItem->isHit()) {
            $metadata = $cacheItem->get();
            Assert::isInstanceOf($metadata, Metadata::class);

            $this->loadedClasses[$document] = $metadata;

            return $this->loadedClasses[$document];
        }

        $metadata = $this->baseMetadataFactory->getMetadataFor($document);

        $this->cache->save($cacheItem->set($metadata));

        return $this->loadedClasses[$document] = $metadata;
    }

    private function escapeClassName(string $class): string
    {
        if (str_contains($class, '@')) {
            // anonymous class: replace all PSR6-reserved characters
            return str_replace(["\0", '\\', '/', '@', ':', '{', '}', '(', ')'], '.', $class);
        }

        return str_replace('\\', '.', $class);
    }
}
