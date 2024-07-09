<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Controller;

use Doctrine\Persistence\ManagerRegistry;
use Meilisearch\Client;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusMeilisearchPlugin\Builder\FilterBuilderInterface;
use Setono\SyliusMeilisearchPlugin\Config\IndexRegistryInterface;
use Setono\SyliusMeilisearchPlugin\Document\Attribute\Facet;
use Setono\SyliusMeilisearchPlugin\Document\Document;
use Setono\SyliusMeilisearchPlugin\Form\Builder\SearchFormBuilderInterface;
use Setono\SyliusMeilisearchPlugin\Form\Type\SearchWidgetType;
use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
use Setono\SyliusMeilisearchPlugin\Resolver\IndexName\IndexNameResolverInterface;
use Sylius\Component\Product\Repository\ProductOptionRepositoryInterface;
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
        private readonly ProductOptionRepositoryInterface $productOptionRepository,
        private readonly string $searchIndex,
        private readonly int $hitsPerPage,
    ) {
        $this->managerRegistry = $managerRegistry;
    }

    public function search(Request $request, SearchFormBuilderInterface $searchFormBuilder, FilterBuilderInterface $filterBuilder): Response
    {
        $q = $request->query->get('q');
        Assert::nullOrString($q);

        $index = $this->indexRegistry->get($this->searchIndex);

        $items = [];

        $searchResult = $this->client->index($this->indexNameResolver->resolve($index))->search($q, [
            'facets' => $this->getFacets($index->document),
            'filter' => $filterBuilder->build($request),
            'sort' => ['price:asc'], // todo doesn't work for some reason...?
            'hitsPerPage' => $this->hitsPerPage,
        ]);

        $searchForm = $searchFormBuilder->build($searchResult);
        $searchForm->handleRequest($request);

        dump($searchResult);

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

    /**
     * @param class-string<Document> $document
     *
     * @return list<string>
     */
    private function getFacets(string $document): array
    {
        $facets = [];

        $documentReflection = new \ReflectionClass($document);
        foreach ($documentReflection->getProperties() as $reflectionProperty) {
            foreach ($reflectionProperty->getAttributes() as $reflectionAttribute) {
                $attribute = $reflectionAttribute->newInstance();
                if ($attribute instanceof Facet) {
                    $facets[] = $reflectionProperty->getName();
                }
            }
        }

        foreach ($documentReflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $reflectionMethod) {
            $property = self::getterProperty($reflectionMethod);
            if (null === $property) {
                continue;
            }

            foreach ($reflectionMethod->getAttributes() as $reflectionAttribute) {
                $attribute = $reflectionAttribute->newInstance();

                if ($attribute instanceof Facet) {
                    $facets[] = $property;
                }
            }
        }

        return $facets;
    }

    private static function getterProperty(\ReflectionMethod $reflectionMethod): ?string
    {
        if ($reflectionMethod->getNumberOfParameters() > 0) {
            return null;
        }

        $name = $reflectionMethod->getName();

        foreach (['get', 'is', 'has'] as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return lcfirst(substr($name, strlen($prefix)));
            }
        }

        return null;
    }
}
