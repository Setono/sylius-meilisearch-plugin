<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Settings;

/**
 * todo add descriptions from docs
 *
 * See https://www.meilisearch.com/docs/reference/api/settings
 */
class Settings
{
    /**
     * Fields displayed in the returned documents
     */
    public UniqueList $displayedAttributes;

    /**
     * Fields in which to search for matching query words sorted by order of importance
     */
    public UniqueList $searchableAttributes;

    /**
     * Attributes to use as filters and facets
     */
    public UniqueList $filterableAttributes;

    /**
     * Attributes to use when sorting search results
     */
    public UniqueList $sortableAttributes;

    public UniqueList $rankingRules;

    /**
     * List of words ignored by Meilisearch when present in search queries
     */
    public UniqueList $stopWords;

    /**
     * List of characters not delimiting where one term begins and ends
     */
    public UniqueList $nonSeparatorTokens;

    /**
     * List of characters delimiting where one term begins and ends
     */
    public UniqueList $separatorTokens;

    /**
     * List of strings Meilisearch should parse as a single term
     */
    public UniqueList $dictionary;

    /** @var array<non-empty-string, list<non-empty-string>> */
    public array $synonyms = [];

    /**
     * Search returns documents with distinct (different) values of the given field
     */
    public ?string $distinctAttribute = null;

    public TypoTolerance $typoTolerance;

    public Faceting $faceting;

    public Pagination $pagination;

    public string $proximityPrecision = 'byWord';

    public ?int $searchCutoffMs = null;

    public function __construct()
    {
        $this->displayedAttributes = new UniqueList(ifEmpty: ['*']);
        $this->searchableAttributes = new UniqueList(ifEmpty: ['*']);
        $this->filterableAttributes = new UniqueList();
        $this->sortableAttributes = new UniqueList();
        $this->rankingRules = new UniqueList([
            'words',
            'typo',
            'proximity',
            'attribute',
            'sort',
            'exactness',
        ]);
        $this->stopWords = new UniqueList();
        $this->nonSeparatorTokens = new UniqueList();
        $this->separatorTokens = new UniqueList();
        $this->dictionary = new UniqueList();
        $this->typoTolerance = new TypoTolerance();
        $this->faceting = new Faceting();
        $this->pagination = new Pagination();
    }
}
