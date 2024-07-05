<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Controller;

use Doctrine\Persistence\ManagerRegistry;
use Meilisearch\Client;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusMeilisearchPlugin\Config\IndexRegistryInterface;
use Setono\SyliusMeilisearchPlugin\Document\Attribute\Facet;
use Setono\SyliusMeilisearchPlugin\Document\Document;
use Setono\SyliusMeilisearchPlugin\Form\Type\SearchWidgetType;
use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
use Setono\SyliusMeilisearchPlugin\Resolver\IndexName\IndexNameResolverInterface;
use Sylius\Component\Product\Repository\ProductOptionRepositoryInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

final class SearchController
{
    use ORMTrait;

    public function __construct(
        ManagerRegistry $managerRegistry,
        private readonly Environment $twig,
        private readonly IndexNameResolverInterface $indexNameResolver,
        private readonly IndexRegistryInterface $indexRegistry,
        private readonly Client $client,
        private readonly ProductOptionRepositoryInterface $productOptionRepository,
        /** @var list<string> $searchIndexes */
        private readonly array $searchIndexes,
    ) {
        $this->managerRegistry = $managerRegistry;
    }

    public function search(Request $request): Response
    {
        $indexes = array_map(fn (string $searchIndex) => $this->indexRegistry->get($searchIndex), $this->searchIndexes);

        $items = [];

        foreach ($indexes as $index) {
            $searchResult = $this->client->index($this->indexNameResolver->resolve($index))->search($request->query->getString('q'), [
                'facets' => $this->getFacets($index->document),
            ]);

            /** @var array{entityClass: class-string<IndexableInterface>, entityId: mixed} $hit */
            foreach ($searchResult->getHits() as $hit) {
                $items[] = $this->getManager($hit['entityClass'])->find($hit['entityClass'], $hit['entityId']);
            }
        }

        return new Response($this->twig->render('@SetonoSyliusMeilisearchPlugin/search/index.html.twig', [
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

    /**
     * @param class-string<Document> $document
     *
     * @return list<string>
     */
    private function getFacets(string $document): array
    {
        $facets = [];

        $reflectionClass = new \ReflectionClass($document);
        foreach ($reflectionClass->getProperties() as $reflectionProperty) {
            foreach ($reflectionProperty->getAttributes() as $reflectionAttribute) {
                $attribute = $reflectionAttribute->newInstance();
                if ($attribute instanceof Facet) {
                    $facets[] = $reflectionProperty->getName();
                }
            }
        }

        return $facets;
    }
}
