<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Controller\Action;

use Doctrine\Persistence\ManagerRegistry;
use Psr\EventDispatcher\EventDispatcherInterface;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusMeilisearchPlugin\Engine\SearchEngineInterface;
use Setono\SyliusMeilisearchPlugin\Engine\SearchRequest;
use Setono\SyliusMeilisearchPlugin\Event\Search\SearchRequestCreated;
use Setono\SyliusMeilisearchPlugin\Event\Search\SearchResponseCreated;
use Setono\SyliusMeilisearchPlugin\Event\Search\SearchResponseParametersCreated;
use Setono\SyliusMeilisearchPlugin\Event\Search\SearchResultReceived;
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
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
        $this->managerRegistry = $managerRegistry;
    }

    public function __invoke(Request $request): Response
    {
        $searchRequestCreatedEvent = new SearchRequestCreated($request, SearchRequest::fromRequest($request));
        $this->eventDispatcher->dispatch($searchRequestCreatedEvent);

        $searchResult = $this->searchEngine->execute($searchRequestCreatedEvent->searchRequest);
        $this->eventDispatcher->dispatch(new SearchResultReceived($searchResult));

        $searchForm = $this->searchFormBuilder->build($searchResult);
        $searchForm->handleRequest($request);

        if ($searchForm->isSubmitted() && !$searchForm->isValid()) {
            // todo handle this scenario
        }

        $items = [];
        /** @var array{entityClass: class-string<IndexableInterface>, entityId: mixed} $hit */
        foreach ($searchResult->hits as $hit) {
            $item = $this->getManager($hit['entityClass'])->find($hit['entityClass'], $hit['entityId']);

            // todo if the item doesn't exist in the database, we can in fact end up with an empty $items list
            if (null === $item) {
                continue;
            }

            $items[] = $item;
        }

        $searchResponseParametersCreatedEvent = new SearchResponseParametersCreated('@SetonoSyliusMeilisearchPlugin/search/index.html.twig', [
            'searchResult' => $searchResult,
            'searchForm' => $searchForm->createView(),
            'items' => $items,
        ], $request);

        $this->eventDispatcher->dispatch($searchResponseParametersCreatedEvent);

        $response = new Response(
            $this->twig->render(
                $searchResponseParametersCreatedEvent->template,
                $searchResponseParametersCreatedEvent->context,
            ),
        );

        $this->eventDispatcher->dispatch(new SearchResponseCreated($request, $response));

        return $response;
    }
}
