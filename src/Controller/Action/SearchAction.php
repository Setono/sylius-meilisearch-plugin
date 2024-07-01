<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Controller\Action;

use Psr\EventDispatcher\EventDispatcherInterface;
use Setono\SyliusMeilisearchPlugin\Config\IndexRegistry;
use Setono\SyliusMeilisearchPlugin\Event\ProductIndexEvent;
use Setono\SyliusMeilisearchPlugin\Resolver\IndexName\IndexNameResolverInterface;
use Setono\SyliusMeilisearchPlugin\Resolver\SortBy\SortByResolverInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Component\Taxonomy\Repository\TaxonRepositoryInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;

final class SearchAction
{
    private Environment $twig;

    private IndexNameResolverInterface $indexNameResolver;

    private TaxonRepositoryInterface $taxonRepository;

    private LocaleContextInterface $localeContext;

    private EventDispatcherInterface $eventDispatcher;

    private IndexRegistry $indexRegistry;

    private SortByResolverInterface $sortByResolver;

    public function __construct(
        Environment $twig,
        IndexNameResolverInterface $indexNameResolver,
        TaxonRepositoryInterface $taxonRepository,
        LocaleContextInterface $localeContext,
        EventDispatcherInterface $eventDispatcher,
        IndexRegistry $indexableResourceRegistry,
        SortByResolverInterface $sortByResolver,
    ) {
        $this->twig = $twig;
        $this->indexNameResolver = $indexNameResolver;
        $this->taxonRepository = $taxonRepository;
        $this->localeContext = $localeContext;
        $this->eventDispatcher = $eventDispatcher;
        $this->indexRegistry = $indexableResourceRegistry;
        $this->sortByResolver = $sortByResolver;
    }

    public function __invoke(string $slug): Response
    {
        $locale = $this->localeContext->getLocaleCode();

        $taxon = $this->taxonRepository->findOneBySlug($slug, $locale);
        if (null === $taxon) {
            throw new NotFoundHttpException(sprintf(
                'The taxon with slug "%s" does not exist, is not enabled or is not translated in locale "%s"',
                $slug,
                $locale,
            ));
        }

        try {
            $indexableResource = $this->indexRegistry->getByResource(ProductInterface::class);
        } catch (\InvalidArgumentException $e) {
            throw new NotFoundHttpException($e->getMessage(), $e);
        }

        $index = $this->indexNameResolver->resolve($indexableResource);

        $response = new Response($this->twig->render('@SetonoSyliusMeilisearchPlugin/shop/product/index.html.twig', [
            'index' => $index,
            'taxon' => $taxon,
            'sortBy' => $this->sortByResolver->resolveFromIndexableResource($indexableResource),
        ]));

        $event = new ProductIndexEvent($response, $index, $taxon, $slug, $locale);
        $this->eventDispatcher->dispatch($event);

        return $event->response;
    }
}
