<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\DataMapper;

use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Setono\SyliusMeilisearchPlugin\Document\Document;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\MetadataFactoryInterface;
use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScope;
use Sylius\Component\Core\Model\ImagesAwareInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Webmozart\Assert\Assert;

final class ImageDataMapper implements DataMapperInterface
{
    public function __construct(
        private readonly CacheManager $cacheManager,
        private readonly MetadataFactoryInterface $metadataFactory,
    ) {
    }

    public function map(
        IndexableInterface $source,
        Document $target,
        IndexScope $indexScope,
        array $context = [],
    ): void {
        Assert::true(
            $this->supports($source, $target, $indexScope, $context),
            'The given $source and $target is not supported',
        );

        $metadata = $this->metadataFactory->getMetadataFor($target);
        foreach ($metadata->imageAttributes as $imageAttribute) {
            $image = $imageAttribute->type === null ? $source->getImages()->first() : $source->getImagesByType($imageAttribute->type)->first();
            if (false === $image) {
                continue;
            }

            $imageUrl = $this->cacheManager->getBrowserPath(
                path: (string) $image->getPath(),
                filter: $imageAttribute->filterSet,
                referenceType: UrlGeneratorInterface::ABSOLUTE_PATH,
            );

            $target->{$imageAttribute->name} = $imageUrl;
        }
    }

    /**
     * @psalm-assert-if-true ImagesAwareInterface $source
     */
    public function supports(
        IndexableInterface $source,
        Document $target,
        IndexScope $indexScope,
        array $context = [],
    ): bool {
        return $source instanceof ImagesAwareInterface && $this->metadataFactory->getMetadataFor($target)->imageAttributes !== [];
    }
}
