<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Controller\Action;

use Meilisearch\Client;
use Setono\SyliusMeilisearchPlugin\Config\IndexRegistryInterface;
use Setono\SyliusMeilisearchPlugin\Resolver\IndexName\IndexNameResolverInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

final class SearchAction
{
    public function __construct(
        private readonly Environment $twig,
        private readonly IndexNameResolverInterface $indexNameResolver,
        private readonly IndexRegistryInterface $indexRegistry,
        private readonly Client $client,
        /** @var list<string> $searchIndexes */
        private readonly array $searchIndexes,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $indexNames = array_map(fn (string $searchIndex) => $this->indexNameResolver->resolve($this->indexRegistry->get($searchIndex)), $this->searchIndexes);

        foreach ($indexNames as $indexName) {
            $searchResult = $this->client->index($indexName)->search($request->query->getString('q'));
            dd($searchResult);
        }

        return new Response($this->twig->render('@SetonoSyliusMeilisearchPlugin/search/index.html.twig', [
            'items' => [],
        ]));
    }
}
