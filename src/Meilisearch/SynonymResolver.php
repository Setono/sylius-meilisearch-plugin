<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Meilisearch;

use Setono\SyliusMeilisearchPlugin\Model\SynonymInterface;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScope;
use Setono\SyliusMeilisearchPlugin\Repository\SynonymRepositoryInterface;

final class SynonymResolver implements SynonymResolverInterface
{
    public function __construct(private readonly SynonymRepositoryInterface $synonymRepository)
    {
    }

    public function resolve(IndexScope $indexScope): array
    {
        // it doesn't make sense to resolve synonyms if the locale is not set
        if (null === $indexScope->localeCode) {
            return [];
        }

        /** @var list<array{term: non-empty-string, synonym: non-empty-string}> $synonyms */
        $synonyms = array_map(static function (SynonymInterface $synonym): array {
            return [
                'term' => (string) $synonym->getTerm(),
                'synonym' => (string) $synonym->getSynonym(),
            ];
        }, $this->synonymRepository->findByLocaleAndChannel($indexScope->localeCode, $indexScope->channelCode));

        $resolvedSynonyms = [];
        foreach ($synonyms as $synonym) {
            $resolvedSynonyms[$synonym['term']][] = $synonym['synonym'];
        }

        foreach ($resolvedSynonyms as $term => $synonyms) {
            $resolvedSynonyms[$term] = array_values(array_unique($synonyms));
        }

        return $resolvedSynonyms;
    }
}
