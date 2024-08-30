<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Form\Builder;

use Setono\SyliusMeilisearchPlugin\Document\Metadata\Facet;
use Setono\SyliusMeilisearchPlugin\Form\Type\RangeType;
use Symfony\Component\Form\FormBuilderInterface;
use function Symfony\Component\String\u;

final class RangeFacetFormBuilder implements FacetFormBuilderInterface
{
    public function build(FormBuilderInterface $builder, string $name, array $values, Facet $facet, array $stats = null): void
    {
        if ($stats === null || !isset($stats['min']) && !isset($stats['max'])) {
            return;
        }

        $builder->add($name, RangeType::class, [
            'label' => u($name)->snake(),
            'required' => false,
        ]);
    }

    public function supports(string $name, array $values, Facet $facet, array $stats = null): bool
    {
        if ($facet->type !== 'float') {
            return false;
        }

//        $keys = array_keys($values);
//        if (count($keys) < 2) {
//            return false;
//        }
//
//        foreach ($keys as $key) {
//            if (is_numeric($key)) {
//                return false;
//            }
//        }

        return true;
    }
}
