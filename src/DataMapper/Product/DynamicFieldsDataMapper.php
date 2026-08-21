<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\DataMapper\Product;

use Setono\SyliusMeilisearchPlugin\DataMapper\DataMapperInterface;
use Setono\SyliusMeilisearchPlugin\DataMapper\Product\Provider\DataMapperValuesProviderInterface;
use Setono\SyliusMeilisearchPlugin\Document\Document;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\DynamicField;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\MetadataFactoryInterface;
use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScope;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Product\Model\ProductOptionValueInterface;
use Webmozart\Assert\Assert;

/**
 * Fills the document's $dynamicFields bag with the values of the dynamic fields, i.e. the product
 * attributes/options that were configured to be indexed in the admin (see the IndexableAttribute
 * and IndexableOption resources).
 *
 * Both product and product variant sources are supported: a variant document gets the attributes of
 * its product and its own option values (where a product document gets the option values of all its
 * enabled variants)
 */
final class DynamicFieldsDataMapper implements DataMapperInterface
{
    public function __construct(
        private readonly MetadataFactoryInterface $metadataFactory,
        private readonly DataMapperValuesProviderInterface $attributesValuesProvider,
        private readonly DataMapperValuesProviderInterface $optionsValuesProvider,
    ) {
    }

    public function map(IndexableInterface $source, Document $target, IndexScope $indexScope, array $context = []): void
    {
        Assert::true($this->supports($source, $target, $indexScope, $context));

        $dynamicFields = $this->metadataFactory->getMetadataFor($target, $indexScope->index)->dynamicFields;

        $product = $source instanceof ProductVariantInterface ? $source->getProduct() : $source;

        /** @var array<string, bool|float|int|string|list<string>>|null $attributeValues */
        $attributeValues = null;

        /** @var array<string, bool|float|int|string|list<string>>|null $optionValues */
        $optionValues = null;

        foreach ($dynamicFields as $field) {
            if (DynamicField::SOURCE_ATTRIBUTE === $field->source) {
                if (null === $product) {
                    continue;
                }

                if (null === $attributeValues) {
                    $attributeValues = $this->attributesValuesProvider->provide($product, $indexScope);
                }

                $value = $attributeValues[$field->code] ?? null;
            } else {
                if (null === $optionValues) {
                    $optionValues = $source instanceof ProductVariantInterface
                        ? self::provideVariantOptions($source)
                        : $this->optionsValuesProvider->provide($source, $indexScope);
                }

                $value = $optionValues[$field->code] ?? null;
            }

            if (null === $value) {
                continue;
            }

            $value = self::coerce($value, $field->type);
            if (null === $value) {
                continue;
            }

            $target->dynamicFields[$field->name] = $value;
        }
    }

    /**
     * @psalm-assert-if-true ProductInterface|ProductVariantInterface $source
     * @psalm-assert-if-true !null $indexScope->localeCode
     */
    public function supports(IndexableInterface $source, Document $target, IndexScope $indexScope, array $context = []): bool
    {
        return ($source instanceof ProductInterface || $source instanceof ProductVariantInterface) &&
            null !== $indexScope->localeCode &&
            $this->metadataFactory->getMetadataFor($target, $indexScope->index)->dynamicFields !== []
        ;
    }

    /**
     * A variant document carries the variant's own option values, not the option values
     * of all the product's variants
     *
     * @return array<string, list<string>>
     */
    private static function provideVariantOptions(ProductVariantInterface $variant): array
    {
        /** @var array<string, list<string>> $options */
        $options = [];

        /** @var ProductOptionValueInterface $optionValue */
        foreach ($variant->getOptionValues() as $optionValue) {
            $option = $optionValue->getOptionCode();
            if ($option === null) {
                continue;
            }

            $options[$option][] = (string) $optionValue->getValue();
        }

        foreach ($options as $option => $values) {
            $options[$option] = array_values(array_unique($values));
        }

        return $options;
    }

    /**
     * Coerces a provided value to the field type. Notice that a scalar value is wrapped in a list for
     * 'array' fields (e.g. a text attribute or a single select) so single and multi valued sources
     * produce the same field shape, and that ints become floats (the search side has no 'int' type)
     *
     * @param bool|float|int|string|list<string> $value
     *
     * @return bool|float|string|list<string>|null
     */
    private static function coerce(bool|float|int|string|array $value, string $type): bool|float|string|array|null
    {
        if ('array' === $type) {
            if (is_array($value)) {
                return array_values($value);
            }

            return is_bool($value) ? null : [(string) $value];
        }

        if (is_array($value)) {
            return null;
        }

        return match ($type) {
            'bool' => (bool) $value,
            'float' => is_numeric($value) ? (float) $value : null,
            'string' => is_bool($value) ? null : (string) $value,
            default => null,
        };
    }
}
