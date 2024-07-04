<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Controller\Action;

use Doctrine\Persistence\ManagerRegistry;
use Meilisearch\Client;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusMeilisearchPlugin\Config\IndexRegistryInterface;
use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
use Setono\SyliusMeilisearchPlugin\Resolver\IndexName\IndexNameResolverInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

final class SearchAction
{
    use ORMTrait;

    public function __construct(
        ManagerRegistry $managerRegistry,
        private readonly Environment $twig,
        private readonly IndexNameResolverInterface $indexNameResolver,
        private readonly IndexRegistryInterface $indexRegistry,
        private readonly Client $client,
        /** @var list<string> $searchIndexes */
        private readonly array $searchIndexes,
    ) {
        $this->managerRegistry = $managerRegistry;
    }

    public function __invoke(Request $request): Response
    {
        $indexNames = array_map(fn (string $searchIndex) => $this->indexNameResolver->resolve($this->indexRegistry->get($searchIndex)), $this->searchIndexes);

        $items = [];

        foreach ($indexNames as $indexName) {
            $searchResult = $this->client->index($indexName)->search($request->query->getString('q'));

            /** @var array{entityClass: class-string<IndexableInterface>, entityId: mixed} $hit */
            foreach ($searchResult->getHits() as $hit) {
                $items[] = $this->getManager($hit['entityClass'])->find($hit['entityClass'], $hit['entityId']);
            }
        }

        return new Response($this->twig->render('@SetonoSyliusMeilisearchPlugin/search/index.html.twig', [
            'items' => $items,
        ]));
    }
}
