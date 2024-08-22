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
        /** @var list<array{term: non-empty-string, synonym: non-empty-string}> $synonyms */
        $synonyms = array_map(static fn (SynonymInterface $synonym): array => [
            'term' => (string) $synonym->getTerm(),
            'synonym' => (string) $synonym->getSynonym(),
        ], $this->synonymRepository->findEnabledByIndexScope($indexScope));

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
