<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\DataMapper\Product;

use Setono\SyliusMeilisearchPlugin\DataMapper\DataMapperInterface;
use Setono\SyliusMeilisearchPlugin\DataMapper\Product\Provider\ReflectionAttributeValuesProviderInterface;
use Setono\SyliusMeilisearchPlugin\Document\Document;
use Setono\SyliusMeilisearchPlugin\Document\Product as ProductDocument;
use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScope;
use Sylius\Component\Core\Model\ProductInterface;
use Webmozart\Assert\Assert;

final class OptionsDataMapper implements DataMapperInterface
{
    public function __construct(private readonly ReflectionAttributeValuesProviderInterface $reflectionAttributeValuesProvider)
    {
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

        $documentReflection = new \ReflectionClass($target);
        foreach ($documentReflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $reflectionProperty) {
            $propertyName = $reflectionProperty->getName();

            foreach ($reflectionProperty->getAttributes() as $reflectionAttribute) {
                try {
                    $values = $this->reflectionAttributeValuesProvider->provide(
                        $reflectionAttribute, $target, $propertyName, $options,
                    );
                } catch (\InvalidArgumentException) {
                    continue;
                }

                $values = array_values(array_unique(array_merge(...$values)));

                /** @psalm-suppress MixedArgument */
                $target->{$propertyName} = array_merge($target->{$propertyName}, $values);
            }
        }
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
