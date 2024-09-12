<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\DataMapper\Product;

use Setono\SyliusMeilisearchPlugin\DataMapper\DataMapperInterface;
use Setono\SyliusMeilisearchPlugin\DataMapper\Product\Setter\DocumentPropertyValuesSetterInterface;
use Setono\SyliusMeilisearchPlugin\Document\Document;
use Setono\SyliusMeilisearchPlugin\Document\Product as ProductDocument;
use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScope;
use Sylius\Component\Core\Model\ProductInterface;
use Webmozart\Assert\Assert;

final class OptionsDataMapper implements DataMapperInterface
{
    public function __construct(
        private readonly DocumentPropertyValuesSetterInterface $documentPropertyValuesSetter,
    ) {
    }

    public function map(IndexableInterface $source, Document $target, IndexScope $indexScope, array $context = []): void
    {
        Assert::true($this->supports($source, $target, $indexScope, $context));

        /** @var array<string, list<string>> $options */
        $options = [];

        foreach ($source->getEnabledVariants() as $variant) {
            foreach ($variant->getOptionValues() as $optionValue) {
                $option = $optionValue->getOptionCode();
                if ($option === null) {
                    continue;
                }

                $options[$option][] = (string) $optionValue->getValue();
            }
        }

        foreach ($options as $option => $values) {
            $options[$option] = array_values(array_unique($values));
        }

        $this->documentPropertyValuesSetter->setFor($target, $options);
    }

    /**
     * @psalm-assert-if-true ProductInterface $source
     * @psalm-assert-if-true ProductDocument $target
     */
    public function supports(IndexableInterface $source, Document $target, IndexScope $indexScope, array $context = []): bool
    {
        return $source instanceof ProductInterface && $target instanceof ProductDocument;
    }
}
