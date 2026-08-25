<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\EventSubscriber;

use Psr\Log\LoggerInterface;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\DynamicField;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\Facet;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\Filterable;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\Metadata;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\Searchable;
use Setono\SyliusMeilisearchPlugin\Event\MetadataCreated;
use Setono\SyliusMeilisearchPlugin\Model\IndexableAttributeInterface;
use Setono\SyliusMeilisearchPlugin\Model\IndexableOptionInterface;
use Setono\SyliusMeilisearchPlugin\Model\IndexableSubjectInterface;
use Setono\SyliusMeilisearchPlugin\Repository\IndexableAttributeRepositoryInterface;
use Setono\SyliusMeilisearchPlugin\Repository\IndexableOptionRepositoryInterface;
use Sylius\Component\Attribute\Model\AttributeValueInterface;
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

    public function __construct(
        private readonly IndexableAttributeRepositoryInterface $indexableAttributeRepository,
        private readonly IndexableOptionRepositoryInterface $indexableOptionRepository,
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
        // Without index context we cannot know which configuration rows apply. And an ineligible
        // index (opted out via the dynamic_fields flag, or not indexing products/variants) must stay
        // untouched even when stale rows still reference it
        if (null === $event->index || !$event->index->supportsDynamicFields()) {
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
        foreach ($rows as $row) {
            $code = $row->getCode();
            if (null === $code || !$this->hasSafeCode($row, 'product attribute')) {
                continue;
            }

            $attribute = $row->getAttribute();
            if (null === $attribute) {
                continue;
            }

            $this->merge(
                $metadata,
                $row,
                DynamicField::SOURCE_ATTRIBUTE,
                sprintf('attr_%s', $code),
                self::resolveFieldType($attribute->getStorageType()),
            );
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
     * @param 'array'|'bool'|'float'|'int'|'string' $fieldType
     */
    private function merge(Metadata $metadata, IndexableSubjectInterface $row, string $source, string $name, string $fieldType): void
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

    private function hasSafeCode(IndexableSubjectInterface $row, string $subject): bool
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
     * Maps a Sylius attribute storage type to the document field type. Notice that date/datetime are
     * mapped to string because they cannot be rendered as facets
     *
     * @return 'array'|'bool'|'float'|'int'|'string'
     */
    private static function resolveFieldType(?string $storageType): string
    {
        return match ($storageType) {
            AttributeValueInterface::STORAGE_BOOLEAN => 'bool',
            AttributeValueInterface::STORAGE_INTEGER => 'int',
            AttributeValueInterface::STORAGE_FLOAT => 'float',
            AttributeValueInterface::STORAGE_DATE, AttributeValueInterface::STORAGE_DATETIME => 'string',
            default => 'array',
        };
    }
}
