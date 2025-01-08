<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\DataMapper\Product\Provider;

use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScope;
use Sylius\Component\Attribute\Model\AttributeInterface;
use Sylius\Component\Attribute\Model\AttributeValueInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Product\Model\ProductAttributeValueInterface;
use Webmozart\Assert\Assert;

final class AttributesDataMapperValuesProvider implements DataMapperValuesProviderInterface
{
    public function provide(IndexableInterface $source, IndexScope $indexScope): array
    {
        Assert::isInstanceOf($source, ProductInterface::class);
        Assert::notNull($indexScope->localeCode);

        $attributes = [];

        /** @var ProductAttributeValueInterface $attributeValue */
        foreach ($source->getAttributesByLocale($indexScope->localeCode, $indexScope->localeCode, $indexScope->localeCode) as $attributeValue) {
            $attribute = $attributeValue->getAttribute();
            Assert::notNull($attribute);

            /** @var mixed $value */
            $value = $attributeValue->getValue();
            $storageType = $attribute->getStorageType();
            Assert::notNull($storageType);

            $convertedValue = match ($storageType) {
                AttributeValueInterface::STORAGE_TEXT => (string) $value,
                AttributeValueInterface::STORAGE_BOOLEAN => (bool) $value,
                AttributeValueInterface::STORAGE_INTEGER => (int) $value,
                AttributeValueInterface::STORAGE_FLOAT => (float) $value,
                AttributeValueInterface::STORAGE_DATE => self::convertDate($value, 'Y-m-d'),
                AttributeValueInterface::STORAGE_DATETIME => self::convertDate($value, \DATE_ATOM),
                AttributeValueInterface::STORAGE_JSON => self::convertJson($value, $attribute, $indexScope->localeCode),
                default => throw new \InvalidArgumentException(sprintf('Unsupported storage type "%s"', $storageType)),
            };

            if (null === $convertedValue || '' === $convertedValue || [] === $convertedValue) {
                continue;
            }

            $attributes[(string) $attribute->getCode()] = $convertedValue;
        }

        return $attributes;
    }

    private static function convertDate(mixed $value, string $format): string
    {
        Assert::isInstanceOf($value, \DateTimeInterface::class);

        return $value->format($format);
    }

    /**
     * Here is an example of how the $values could look like:
     *
     * [
     *   0 => "6717419c-2d88-4118-b821-f4263a95f499"
     *   1 => "ec4ab1af-ed41-4ddc-9330-1af5f5258b71"
     * ]
     *
     * and here is an example of how the $attribute->getConfiguration() could look like:
     *
     * [
     *   "multiple" => true
     *   "choices" => [
     *     "7a968ac4-a1e3-4a37-a707-f22a839130c4" => [
     *       "en_US" => "Red"
     *     ]
     *     "ff62a939-d946-4d6b-b742-b7115875ae75" => [
     *       "en_US" => "Green"
     *     ]
     *     "6717419c-2d88-4118-b821-f4263a95f499" => [
     *       "en_US" => "Black"
     *     ]
     *     "ec4ab1af-ed41-4ddc-9330-1af5f5258b71" => [
     *       "en_US" => "Blue"
     *     ]
     *   ]
     * ]
     */
    private static function convertJson(mixed $values, AttributeInterface $attribute, string $locale): array|string|null
    {
        Assert::isArray($values);
        if ([] === $values) {
            return null;
        }

        $configuration = $attribute->getConfiguration();
        if ([] === $configuration) {
            return null;
        }

        $choices = $configuration['choices'] ?? [];
        if ([] === $choices || !is_array($choices)) {
            return null;
        }

        $ret = [];

        /** @var mixed $value */
        foreach ($values as $value) {
            Assert::string($value);

            if (!isset($choices[$value]) || !is_array($choices[$value])) {
                continue;
            }

            /** @var mixed $resolvedValue */
            $resolvedValue = $choices[$value][$locale] ?? reset($choices[$value]);
            Assert::string($resolvedValue);

            $ret[] = $resolvedValue;
        }

        if ([] === $ret) {
            return null;
        }

        if (array_key_exists('multiple', $configuration) && true === $configuration['multiple']) {
            return $ret;
        }

        return $ret[0];
    }
}
