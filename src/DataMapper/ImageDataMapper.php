<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\DataMapper;

use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Setono\SyliusMeilisearchPlugin\Document\Document;
use Setono\SyliusMeilisearchPlugin\Document\ImageAwareInterface;
use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScope;
use Sylius\Component\Core\Model\ImageInterface;
use Sylius\Component\Core\Model\ImagesAwareInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Webmozart\Assert\Assert;

final class ImageDataMapper implements DataMapperInterface
{
    /**
     * @param array<class-string<IndexableInterface>, string> $entityToFilterSetMapping
     */
    public function __construct(
        private readonly CacheManager $cacheManager,
        private readonly array $entityToFilterSetMapping = [],
        // todo add this to the plugin configuration
        private readonly string $defaultFilterSet = 'sylius_large',
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

        $image = $source->getImages()->first();
        if (!$image instanceof ImageInterface) {
            return;
        }

        $target->setImage($this->cacheManager->getBrowserPath(
            path: (string) $image->getPath(),
            filter: $this->entityToFilterSetMapping[$source::class] ?? $this->defaultFilterSet,
            referenceType: UrlGeneratorInterface::ABSOLUTE_PATH,
        ));
    }

    /**
     * @psalm-assert-if-true ImagesAwareInterface $source
     * @psalm-assert-if-true ImageAwareInterface $target
     */
    public function supports(
        IndexableInterface $source,
        Document $target,
        IndexScope $indexScope,
        array $context = [],
    ): bool {
        return $source instanceof ImagesAwareInterface && $target instanceof ImageAwareInterface;
    }
}
