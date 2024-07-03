<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Controller\Action;

use Psr\EventDispatcher\EventDispatcherInterface;
use Setono\SyliusMeilisearchPlugin\Config\IndexRegistryInterface;
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
    public function __construct(
        private readonly Environment $twig,
        private readonly IndexNameResolverInterface $indexNameResolver,
        private readonly TaxonRepositoryInterface $taxonRepository,
        private readonly LocaleContextInterface $localeContext,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly IndexRegistryInterface $indexRegistry,
        private readonly SortByResolverInterface $sortByResolver,
    ) {
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

        // todo we should get the index from the plugin configuration
        $index = $this->indexNameResolver->resolve(ProductInterface::class);

        $response = new Response($this->twig->render('@SetonoSyliusMeilisearchPlugin/shop/product/index.html.twig', [
            'index' => $index,
            'taxon' => $taxon,
            //'sortBy' => $this->sortByResolver->resolveFromIndexableResource($indexableResource),
        ]));

        $event = new ProductIndexEvent($response, $index, $taxon, $slug, $locale);
        $this->eventDispatcher->dispatch($event);

        return $event->response;
    }
}
