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
    public function build(FormBuilderInterface $builder, string $name, array $values, Facet $facet, array $stats = null): void
    {
        foreach ($this->services as $service) {
            if ($service->supports($name, $values, $facet, $stats)) {
                $service->build($builder, $name, $values, $facet, $stats);

                return;
            }
        }

        throw new \RuntimeException(sprintf('No facet form builder supports the facet with name "%s"', $name));
    }

    public function supports(string $name, array $values, Facet $facet, array $stats = null): bool
    {
        foreach ($this->services as $service) {
            if ($service->supports($name, $values, $facet, $stats)) {
                return true;
            }
        }

        return false;
    }
}
