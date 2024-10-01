<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Controller\Action;

use Doctrine\Persistence\ManagerRegistry;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusMeilisearchPlugin\Engine\SearchEngineInterface;
use Setono\SyliusMeilisearchPlugin\Engine\SearchRequest;
use Setono\SyliusMeilisearchPlugin\Form\Builder\SearchFormBuilderInterface;
use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

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
        $searchResult = $this->searchEngine->execute(SearchRequest::fromRequest($request));

        $searchForm = $this->searchFormBuilder->build($searchResult);
        $searchForm->handleRequest($request);

        if ($searchForm->isSubmitted() && !$searchForm->isValid()) {
            // todo handle this scenario
        }

        $items = [];
        /** @var array{entityClass: class-string<IndexableInterface>, entityId: mixed} $hit */
        foreach ($searchResult->getHits() as $hit) {
            $item = $this->getManager($hit['entityClass'])->find($hit['entityClass'], $hit['entityId']);
            if (null === $item) {
                continue;
            }

            $items[] = $item;
        }

        return new Response($this->twig->render('@SetonoSyliusMeilisearchPlugin/search/index.html.twig', [
            'searchResult' => $searchResult,
            'searchForm' => $searchForm->createView(),
            'items' => $items,
        ]));
    }
}
