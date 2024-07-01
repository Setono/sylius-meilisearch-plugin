<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Event;

use Sylius\Component\Taxonomy\Model\TaxonInterface;
use Symfony\Component\HttpFoundation\Response;

final class ProductIndexEvent
{
    public function __construct(
        public Response $response,
        /** This is the name of the Meilisearch search index */
        public readonly string $index,
        public readonly TaxonInterface $taxon, public readonly string $slug, public readonly string $locale)
    {
    }
}
