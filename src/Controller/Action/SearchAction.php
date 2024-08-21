<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Controller\Action;

use Doctrine\Persistence\ManagerRegistry;
use Meilisearch\Client;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\Facet;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\MetadataFactoryInterface;
use Setono\SyliusMeilisearchPlugin\Form\Builder\SearchFormBuilderInterface;
use Setono\SyliusMeilisearchPlugin\Meilisearch\Builder\FilterBuilderInterface;
use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
use Setono\SyliusMeilisearchPlugin\Resolver\IndexName\IndexNameResolverInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Webmozart\Assert\Assert;

final class SearchAction
{
    use ORMTrait;

    public function __construct(
        ManagerRegistry $managerRegistry,
        private readonly Environment $twig,
        private readonly IndexNameResolverInterface $indexNameResolver,
        private readonly Client $client,
        private readonly MetadataFactoryInterface $metadataFactory,
        private readonly SearchFormBuilderInterface $searchFormBuilder,
        private readonly FilterBuilderInterface $filterBuilder,
        private readonly Index $index,
        private readonly int $hitsPerPage,
    ) {
        $this->managerRegistry = $managerRegistry;
    }

    public function __invoke(Request $request): Response
    {
        $q = $request->query->get('q');
        Assert::nullOrString($q);

        $page = (int) $request->query->get('p', 1);
        $page = max(1, $page);

        $metadata = $this->metadataFactory->getMetadataFor($this->index->document);

        $searchResult = $this->client->index($this->indexNameResolver->resolve($this->index))->search($q, [
            'facets' => array_map(static fn (Facet $facet) => $facet->name, $metadata->getFacets()),
            'filter' => $this->filterBuilder->build($request),
            'sort' => ['price:asc'],
            'hitsPerPage' => $this->hitsPerPage,
            'page' => $page,
        ]);

        $searchForm = $this->searchFormBuilder->build($searchResult);
        $searchForm->handleRequest($request);

        $items = [];

        /** @var array{entityClass: class-string<IndexableInterface>, entityId: mixed} $hit */
        foreach ($searchResult->getHits() as $hit) {
            $items[] = $this->getManager($hit['entityClass'])->find($hit['entityClass'], $hit['entityId']);
        }

        return new Response($this->twig->render('@SetonoSyliusMeilisearchPlugin/search/index.html.twig', [
            'searchResult' => $searchResult,
            'searchForm' => $searchForm->createView(),
            'items' => $items,
        ]));
    }
}
