<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\DataMapper\Product;

use Setono\SyliusMeilisearchPlugin\DataMapper\DataMapperInterface;
use Setono\SyliusMeilisearchPlugin\DataMapper\Product\Provider\DataMapperValuesProviderInterface;
use Setono\SyliusMeilisearchPlugin\Document\Document;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\DynamicField;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\MetadataFactoryInterface;
use Setono\SyliusMeilisearchPlugin\Document\Product as ProductDocument;
use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScope;
use Sylius\Component\Core\Model\ProductInterface;
use Webmozart\Assert\Assert;

/**
 * Fills the document's $attributes bag with the values of the dynamic fields, i.e. the product
 * attributes/options that were configured to be indexed in the admin (see the IndexableAttribute resource)
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

        /** @var array<string, array<string, bool|float|int|string|list<string>>|null> $values */
        $values = [
            DynamicField::SOURCE_ATTRIBUTE => null,
            DynamicField::SOURCE_OPTION => null,
        ];

        foreach ($dynamicFields as $field) {
            if (null === $values[$field->source]) {
                $values[$field->source] = DynamicField::SOURCE_ATTRIBUTE === $field->source
                    ? $this->attributesValuesProvider->provide($source, $indexScope)
                    : $this->optionsValuesProvider->provide($source, $indexScope);
            }

            $value = $values[$field->source][$field->code] ?? null;
            if (null === $value) {
                continue;
            }

            $value = self::coerce($value, $field->type);
            if (null === $value) {
                continue;
            }

            $target->attributes[$field->name] = $value;
        }
    }

    /**
     * @psalm-assert-if-true ProductInterface $source
     * @psalm-assert-if-true ProductDocument $target
     * @psalm-assert-if-true !null $indexScope->localeCode
     */
    public function supports(IndexableInterface $source, Document $target, IndexScope $indexScope, array $context = []): bool
    {
        return $source instanceof ProductInterface &&
            $target instanceof ProductDocument &&
            null !== $indexScope->localeCode &&
            $this->metadataFactory->getMetadataFor($target, $indexScope->index)->dynamicFields !== []
        ;
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
