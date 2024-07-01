<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\DataMapper;

use Setono\SyliusMeilisearchPlugin\Document\Document;
use Setono\SyliusMeilisearchPlugin\Document\UrlAwareInterface;
use Setono\SyliusMeilisearchPlugin\IndexScope\IndexScope;
use Setono\SyliusMeilisearchPlugin\UrlGenerator\ResourceUrlGeneratorInterface;
use Sylius\Component\Resource\Model\ResourceInterface;
use Webmozart\Assert\Assert;

final class UrlDataMapper implements DataMapperInterface
{
    public function __construct(private readonly ResourceUrlGeneratorInterface $urlGenerator)
    {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function map(
        ResourceInterface $source,
        Document $target,
        IndexScope $indexScope,
        array $context = [],
    ): void {
        Assert::true(
            $this->supports($source, $target, $indexScope, $context),
            'The given $source and $target is not supported',
        );

        $target->setUrl($this->urlGenerator->generate($source, ['localeCode' => $indexScope->localeCode]));
    }

    /**
     * @psalm-assert-if-true UrlAwareInterface $target
     * @psalm-assert-if-true !null $indexScope->localeCode
     */
    public function supports(
        ResourceInterface $source,
        Document $target,
        IndexScope $indexScope,
        array $context = [],
    ): bool {
        return $target instanceof UrlAwareInterface && null !== $indexScope->localeCode;
    }
}
