<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Controller\Action;

use Doctrine\Persistence\ManagerRegistry;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusMeilisearchPlugin\Engine\SearchEngineInterface;
use Setono\SyliusMeilisearchPlugin\Engine\SearchRequest;
use Setono\SyliusMeilisearchPlugin\Event\Search\SearchRequestCreated;
use Setono\SyliusMeilisearchPlugin\Event\Search\SearchResponseCreated;
use Setono\SyliusMeilisearchPlugin\Event\Search\SearchResponseParametersCreated;
use Setono\SyliusMeilisearchPlugin\Event\Search\SearchResultReceived;
use Setono\SyliusMeilisearchPlugin\Form\Builder\SearchFormBuilderInterface;
use Setono\SyliusMeilisearchPlugin\Message\Command\RemoveEntity;
use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
use Sylius\Component\Resource\Model\ToggleableInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
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
        private readonly MessageBusInterface $commandBus,
        private readonly LoggerInterface $logger = new NullLogger(),
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

        $items = $this->hydrateHits($searchResult->hits);

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

    /**
     * Hydrates the Meilisearch hits into entities: one findBy() per entity class (instead of one
     * find() per hit), reordered to match Meilisearch's ranking. Hits whose entity is missing from
     * the database or disabled are dropped and a RemoveEntity is dispatched so the index self-heals.
     *
     * @param array<int, array> $hits
     *
     * @return list<IndexableInterface>
     */
    private function hydrateHits(array $hits): array
    {
        // Group hit ids by entity class, preserving Meilisearch's order
        $idsByClass = [];
        foreach ($hits as $hit) {
            $class = $hit['entityClass'] ?? null;
            if (!is_string($class) || !is_a($class, IndexableInterface::class, true)) {
                continue;
            }

            $idsByClass[$class][] = $hit['entityId'] ?? null;
        }

        // Load each class in a single query, keyed by (string) id
        $loaded = [];
        foreach ($idsByClass as $class => $ids) {
            /** @var list<IndexableInterface> $entities */
            $entities = $this->getRepository($class)->findBy(['id' => $ids]);
            foreach ($entities as $entity) {
                $loaded[$class][(string) $entity->getId()] = $entity;
            }
        }

        [$items, $stale] = self::reorderAndFilter($hits, $loaded);

        foreach ($stale as $hit) {
            try {
                $this->commandBus->dispatch(new RemoveEntity($hit['entityClass'], $hit['entityId'], $hit['documentIdentifier']));
            } catch (\Throwable $e) {
                // Self-healing must never break rendering the search page
                $this->logger->error('Failed to dispatch RemoveEntity for a stale search hit: {message}', [
                    'message' => $e->getMessage(),
                    'entity' => $hit['entityClass'],
                    'exception' => $e,
                ]);
            }
        }

        return $items;
    }

    /**
     * Reorders the loaded entities to match Meilisearch's hit order and separates out the stale
     * hits (missing from the database, or disabled) so the caller can self-heal the index.
     *
     * @param array<int, array<array-key, mixed>> $hits
     * @param array<string, array<array-key, IndexableInterface>> $loaded
     *
     * @return array{0: list<IndexableInterface>, 1: list<array{entityClass: class-string<IndexableInterface>, entityId: int|string, documentIdentifier: string|null}>}
     */
    public static function reorderAndFilter(array $hits, array $loaded): array
    {
        $items = [];
        $stale = [];

        foreach ($hits as $hit) {
            $class = $hit['entityClass'] ?? null;
            $id = $hit['entityId'] ?? null;

            if (!is_string($class) || !is_a($class, IndexableInterface::class, true) || (!is_string($id) && !is_int($id))) {
                continue;
            }

            $entity = $loaded[$class][(string) $id] ?? null;

            // A hit whose entity was deleted, or that is now disabled, must not be rendered (the
            // index is stale); dispatch a removal so it heals on the next request instead. The
            // document is removed by its own Meilisearch primary key ('id'), captured from the hit.
            if (null === $entity || ($entity instanceof ToggleableInterface && !$entity->isEnabled())) {
                $documentIdentifier = $hit['id'] ?? null;

                $stale[] = [
                    'entityClass' => $class,
                    'entityId' => $id,
                    'documentIdentifier' => is_scalar($documentIdentifier) ? (string) $documentIdentifier : null,
                ];

                continue;
            }

            $items[] = $entity;
        }

        return [$items, $stale];
    }
}
