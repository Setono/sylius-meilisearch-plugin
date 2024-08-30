<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Form\Builder;

use Setono\CompositeCompilerPass\CompositeService;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\Facet;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * @extends CompositeService<FacetFormBuilderInterface>
 */
final class CompositeFacetFormBuilder extends CompositeService implements FacetFormBuilderInterface
{
    public function build(FormBuilderInterface $builder, array $values, Facet $facet, array $stats = null): void
    {
        foreach ($this->services as $service) {
            if ($service->supports($values, $facet, $stats)) {
                $service->build($builder, $values, $facet, $stats);

                return;
            }
        }

        throw new \RuntimeException(sprintf('No facet form builder supports the facet with name "%s"', $facet->name));
    }

    public function supports(array $values, Facet $facet, array $stats = null): bool
    {
        foreach ($this->services as $service) {
            if ($service->supports($values, $facet, $stats)) {
                return true;
            }
        }

        return false;
    }
}
