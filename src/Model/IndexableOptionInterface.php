<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Model;

use Sylius\Component\Product\Model\ProductOptionInterface;
use Sylius\Component\Resource\Model\ResourceInterface;
use Sylius\Component\Resource\Model\TimestampableInterface;
use Sylius\Component\Resource\Model\ToggleableInterface;

/**
 * Configures a Sylius product option to be indexed in Meilisearch
 */
interface IndexableOptionInterface extends ResourceInterface, ToggleableInterface, TimestampableInterface
{
    public function getId(): ?int;

    public function getOption(): ?ProductOptionInterface;

    public function setOption(?ProductOptionInterface $option): void;

    /**
     * The code of the associated product option
     */
    public function getCode(): ?string;

    /**
     * @return list<string>
     */
    public function getIndexes(): array;

    public function addIndex(string $index): void;

    public function removeIndex(string $index): void;

    public function hasIndex(string $index): bool;

    public function isSearchable(): bool;

    public function setSearchable(bool $searchable): void;

    public function isFilterable(): bool;

    public function setFilterable(bool $filterable): void;

    public function isFacetable(): bool;

    public function setFacetable(bool $facetable): void;

    public function getFacetPosition(): int;

    public function setFacetPosition(int $facetPosition): void;
}
