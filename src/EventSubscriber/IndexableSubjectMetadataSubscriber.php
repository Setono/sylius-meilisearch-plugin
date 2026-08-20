<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\EventSubscriber;

use Psr\Log\LoggerInterface;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\DynamicField;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\Facet;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\Filterable;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\Metadata;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\Searchable;
use Setono\SyliusMeilisearchPlugin\Document\Product;
use Setono\SyliusMeilisearchPlugin\Event\MetadataCreated;
use Setono\SyliusMeilisearchPlugin\Model\IndexableAttributeInterface;
use Setono\SyliusMeilisearchPlugin\Model\IndexableOptionInterface;
use Setono\SyliusMeilisearchPlugin\Repository\IndexableAttributeRepositoryInterface;
use Setono\SyliusMeilisearchPlugin\Repository\IndexableOptionRepositoryInterface;
use Sylius\Component\Attribute\Model\AttributeValueInterface;
use Sylius\Component\Product\Model\ProductAttributeInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Merges the admin configured IndexableAttribute/IndexableOption rows into the document metadata of the
 * index the metadata is resolved for. This subscriber must never call MetadataFactoryInterface::getMetadataFor()
 * because the MetadataCreated event is dispatched before the metadata is memoized (it would recurse)
 */
final class IndexableSubjectMetadataSubscriber implements EventSubscriberInterface
{
    /**
     * The field name ends up unquoted in Meilisearch filter expressions and as a Symfony form
     * field name, so the code it is built from must be strictly alphanumeric with _ and -
     */
    private const CODE_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9_-]*$/';

    /**
     * @param RepositoryInterface<ProductAttributeInterface> $productAttributeRepository
     */
    public function __construct(
        private readonly IndexableAttributeRepositoryInterface $indexableAttributeRepository,
        private readonly IndexableOptionRepositoryInterface $indexableOptionRepository,
        private readonly RepositoryInterface $productAttributeRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            MetadataCreated::class => 'onMetadataCreated',
        ];
    }

    public function onMetadataCreated(MetadataCreated $event): void
    {
        // Without index context we cannot know which configuration rows apply,
        // and only product documents carry product attributes/options
        if (null === $event->index || !is_a($event->metadata->document, Product::class, true)) {
            return;
        }

        $this->mergeAttributes($event->metadata, $this->indexableAttributeRepository->findEnabledByIndex($event->index->name));
        $this->mergeOptions($event->metadata, $this->indexableOptionRepository->findEnabledByIndex($event->index->name));
    }

    /**
     * @param array<array-key, IndexableAttributeInterface> $rows
     */
    private function mergeAttributes(Metadata $metadata, array $rows): void
    {
        if ([] === $rows) {
            return;
        }

        $storageTypes = $this->getStorageTypes($rows);

        foreach ($rows as $row) {
            $code = $row->getCode();
            if (null === $code || !$this->hasSafeCode($row, 'product attribute')) {
                continue;
            }

            if (!isset($storageTypes[$code])) {
                $this->logger->warning(sprintf(
                    'The product attribute "%s" is configured to be indexed, but it does not exist (anymore). It was skipped',
                    $code,
                ));

                continue;
            }

            $fieldType = self::resolveFieldType($storageTypes[$code]);

            $this->merge($metadata, $row, DynamicField::SOURCE_ATTRIBUTE, sprintf('attr_%s', $code), $fieldType);
        }
    }

    /**
     * @param array<array-key, IndexableOptionInterface> $rows
     */
    private function mergeOptions(Metadata $metadata, array $rows): void
    {
        foreach ($rows as $row) {
            $code = $row->getCode();
            if (null === $code || !$this->hasSafeCode($row, 'product option')) {
                continue;
            }

            // option values are always lists of strings
            $this->merge($metadata, $row, DynamicField::SOURCE_OPTION, sprintf('opt_%s', $code), 'array');
        }
    }

    /**
     * @param DynamicField::SOURCE_* $source
     * @param 'array'|'bool'|'float'|'string' $fieldType
     */
    private function merge(Metadata $metadata, IndexableAttributeInterface|IndexableOptionInterface $row, string $source, string $name, string $fieldType): void
    {
        $code = (string) $row->getCode();

        if (isset($metadata->dynamicFields[$name]) ||
            isset($metadata->facetableAttributes[$name]) ||
            isset($metadata->filterableAttributes[$name]) ||
            isset($metadata->searchableAttributes[$name]) ||
            isset($metadata->sortableAttributes[$name])
        ) {
            $this->logger->warning(sprintf(
                'The code "%s" is configured to be indexed, but the field name "%s" is already used on the document %s. It was skipped',
                $code,
                $name,
                $metadata->document,
            ));

            return;
        }

        $metadata->dynamicFields[$name] = new DynamicField($name, $source, $code, $fieldType);

        if ($row->isSearchable()) {
            $metadata->searchableAttributes[$name] = new Searchable($name);
        }

        // A facetable field MUST also be filterable: the settings provider only pushes the filterable
        // attribute names to Meilisearch, and requesting a facet that is not filterable fails the search
        if ($row->isFilterable() || $row->isFacetable()) {
            $metadata->filterableAttributes[$name] = new Filterable($name);
        }

        if ($row->isFacetable()) {
            if ('string' === $fieldType) {
                $this->logger->warning(sprintf(
                    'The product attribute "%s" is configured as facetable, but date attributes cannot be rendered as facets. The facet was skipped',
                    $code,
                ));
            } else {
                $metadata->facetableAttributes[$name] = new Facet($name, $fieldType, $row->getFacetPosition());
            }
        }
    }

    private function hasSafeCode(IndexableAttributeInterface|IndexableOptionInterface $row, string $subject): bool
    {
        if (1 === preg_match(self::CODE_PATTERN, (string) $row->getCode())) {
            return true;
        }

        $this->logger->warning(sprintf(
            'The %s "%s" is configured to be indexed, but its code contains characters that are not allowed in a Meilisearch attribute name. It was skipped',
            $subject,
            (string) $row->getCode(),
        ));

        return false;
    }

    /**
     * Returns the storage types of the product attributes referenced by the given rows, indexed by code
     *
     * @param array<array-key, IndexableAttributeInterface> $rows
     *
     * @return array<string, string|null>
     */
    private function getStorageTypes(array $rows): array
    {
        $codes = [];
        foreach ($rows as $row) {
            if (null !== $row->getCode()) {
                $codes[] = $row->getCode();
            }
        }

        if ([] === $codes) {
            return [];
        }

        $storageTypes = [];
        foreach ($this->productAttributeRepository->findBy(['code' => $codes]) as $attribute) {
            $storageTypes[(string) $attribute->getCode()] = $attribute->getStorageType();
        }

        return $storageTypes;
    }

    /**
     * Maps a Sylius attribute storage type to the document field type. Notice that integers are mapped to
     * float because the search side has no filter builder for the 'int' type, and date/datetime are mapped
     * to string because they cannot be rendered as facets
     *
     * @return 'array'|'bool'|'float'|'string'
     */
    private static function resolveFieldType(?string $storageType): string
    {
        return match ($storageType) {
            AttributeValueInterface::STORAGE_BOOLEAN => 'bool',
            AttributeValueInterface::STORAGE_INTEGER, AttributeValueInterface::STORAGE_FLOAT => 'float',
            AttributeValueInterface::STORAGE_DATE, AttributeValueInterface::STORAGE_DATETIME => 'string',
            default => 'array',
        };
    }
}
