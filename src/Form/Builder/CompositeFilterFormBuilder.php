<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Form\Builder;

use Setono\CompositeCompilerPass\CompositeService;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\Facet;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * @extends CompositeService<FilterFormBuilderInterface>
 */
final class CompositeFilterFormBuilder extends CompositeService implements FilterFormBuilderInterface
{
    public function build(FormBuilderInterface $builder, Facet $facet, array $values, array $stats = null): void
    {
        foreach ($this->services as $service) {
            if ($service->supports($facet, $values, $stats)) {
                $service->build($builder, $facet, $values, $stats);

                return;
            }
        }

        throw new \RuntimeException(sprintf('No facet form builder supports the facet with name "%s"', $facet->name));
    }

    public function supports(Facet $facet, array $values, array $stats = null): bool
    {
        foreach ($this->services as $service) {
            if ($service->supports($facet, $values, $stats)) {
                return true;
            }
        }

        return false;
    }
}
