<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Controller;

use Doctrine\Persistence\ManagerRegistry;
use Meilisearch\Client;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusMeilisearchPlugin\Config\IndexRegistryInterface;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\Facet;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\MetadataFactoryInterface;
use Setono\SyliusMeilisearchPlugin\Form\Builder\SearchFormBuilderInterface;
use Setono\SyliusMeilisearchPlugin\Form\Type\SearchWidgetType;
use Setono\SyliusMeilisearchPlugin\Meilisearch\Builder\FilterBuilderInterface;
use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
use Setono\SyliusMeilisearchPlugin\Resolver\IndexName\IndexNameResolverInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Webmozart\Assert\Assert;

final class SearchController
{
    use ORMTrait;

    public function __construct(
        ManagerRegistry $managerRegistry,
        private readonly Environment $twig,
        private readonly IndexNameResolverInterface $indexNameResolver,
        private readonly IndexRegistryInterface $indexRegistry,
        private readonly Client $client,
        private readonly MetadataFactoryInterface $metadataFactory,
        private readonly string $searchIndex,
        private readonly int $hitsPerPage,
    ) {
        $this->managerRegistry = $managerRegistry;
    }

    public function search(Request $request, SearchFormBuilderInterface $searchFormBuilder, FilterBuilderInterface $filterBuilder): Response
    {
        $q = $request->query->get('q');
        Assert::nullOrString($q);

        $page = (int) $request->query->get('p', 1);
        $page = max(1, $page);

        $index = $this->indexRegistry->get($this->searchIndex);

        $metadata = $this->metadataFactory->getMetadataFor($index->document);

        $searchResult = $this->client->index($this->indexNameResolver->resolve($index))->search($q, [
            'facets' => array_map(static fn (Facet $facet) => $facet->name, $metadata->getFacets()),
            'filter' => $filterBuilder->build($request),
            'sort' => ['price:asc'], // todo doesn't work for some reason...?
            'hitsPerPage' => $this->hitsPerPage,
            'page' => $page,
        ]);

        $searchForm = $searchFormBuilder->build($searchResult);
        $searchForm->handleRequest($request);

        dump($searchResult);

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

    public function widget(FormFactoryInterface $formFactory): Response
    {
        $form = $formFactory->createNamed('', SearchWidgetType::class);

        return new Response($this->twig->render('@SetonoSyliusMeilisearchPlugin/search/widget/content.html.twig', [
            'form' => $form->createView(),
        ]));
    }
}
