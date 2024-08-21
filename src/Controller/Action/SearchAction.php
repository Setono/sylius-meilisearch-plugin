<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Controller\Action;

use Doctrine\Persistence\ManagerRegistry;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusMeilisearchPlugin\Engine\SearchEngineInterface;
use Setono\SyliusMeilisearchPlugin\Form\Builder\SearchFormBuilderInterface;
use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
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
        private readonly SearchFormBuilderInterface $searchFormBuilder,
        private readonly SearchEngineInterface $searchEngine,
    ) {
        $this->managerRegistry = $managerRegistry;
    }

    public function __invoke(Request $request): Response
    {
        $query = $request->query->get('q');
        Assert::nullOrString($query);

        $searchResult = $this->searchEngine->execute($query, $request->query->all());

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
