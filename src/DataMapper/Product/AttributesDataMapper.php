<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\DataMapper\Product;

use Setono\SyliusMeilisearchPlugin\DataMapper\DataMapperInterface;
use Setono\SyliusMeilisearchPlugin\DataMapper\Product\Provider\DataMapperValuesProviderInterface;
use Setono\SyliusMeilisearchPlugin\DataMapper\Product\Setter\DocumentPropertyValuesSetterInterface;
use Setono\SyliusMeilisearchPlugin\Document\Document;
use Setono\SyliusMeilisearchPlugin\Document\Product as ProductDocument;
use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScope;
use Sylius\Component\Core\Model\ProductInterface;
use Webmozart\Assert\Assert;

final class AttributesDataMapper implements DataMapperInterface
{
    public function __construct(
        private readonly DataMapperValuesProviderInterface $dataMapperValuesProvider,
        private readonly DocumentPropertyValuesSetterInterface $documentPropertyValuesSetter,
    ) {
    }

    public function map(IndexableInterface $source, Document $target, IndexScope $indexScope, array $context = []): void
    {
        Assert::true($this->supports($source, $target, $indexScope, $context));

        $attributes = $this->dataMapperValuesProvider->provide($source, $indexScope);
        $this->documentPropertyValuesSetter->setFor($target, $attributes);
    }

    /**
     * @psalm-assert-if-true ProductInterface $source
     * @psalm-assert-if-true ProductDocument $target
     * @psalm-assert-if-true !null $indexScope->localeCode
     */
    public function supports(IndexableInterface $source, Document $target, IndexScope $indexScope, array $context = []): bool
    {
        return $source instanceof ProductInterface && $target instanceof ProductDocument && null !== $indexScope->localeCode;
    }
}
