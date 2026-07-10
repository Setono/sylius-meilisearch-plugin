<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Form\Builder;

use Psr\Container\ContainerInterface;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\Facet;
use Setono\SyliusMeilisearchPlugin\Engine\FacetValues;
use Setono\SyliusMeilisearchPlugin\Form\Builder\Sorter\FilterValuesSorterInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use function Symfony\Component\String\u;
use Webmozart\Assert\Assert;

final class ChoiceFilterFormBuilder implements FilterFormBuilderInterface
{
    /**
     * @param ContainerInterface $sorterLocator A service locator of tagged
     *   FilterValuesSorterInterface services, keyed by service id
     */
    public function __construct(private readonly ContainerInterface $sorterLocator)
    {
    }

    public function build(FormBuilderInterface $builder, Facet $facet, FacetValues $values): void
    {
        $choices = array_combine($values->getValues(), $values->getValues());

        if ($facet->sorter !== null) {
            $choices = $this->resolveSorter($facet->sorter)->sort($choices);
        }

        $builder->add($facet->name, ChoiceType::class, [
            'label' => sprintf('setono_sylius_meilisearch.form.search.facet.%s', u($facet->name)->snake()),
            'choices' => $choices,
            'choice_label' => fn (string $value) => sprintf('%s (%d)', $value, $values->getValueCount($value)),
            'expanded' => true,
            'multiple' => true,
            'required' => false,
            'block_prefix' => 'setono_sylius_meilisearch_facet_choice',
            'priority' => -1 * $facet->position,
        ]);
    }

    /**
     * Resolves a facet sorter from the service locator of tagged
     * setono_sylius_meilisearch.filter_values_sorter services (the shipped SizeSorter is registered
     * under its FQCN, so #[Facetable(sorter: SizeSorter::class)] keeps working).
     */
    private function resolveSorter(string $sorterId): FilterValuesSorterInterface
    {
        Assert::true(
            $this->sorterLocator->has($sorterId),
            sprintf(
                'No facet sorter "%s" is registered. Tag it with "setono_sylius_meilisearch.filter_values_sorter" (autoconfiguration does this for %s implementations).',
                $sorterId,
                FilterValuesSorterInterface::class,
            ),
        );

        $sorter = $this->sorterLocator->get($sorterId);
        Assert::isInstanceOf($sorter, FilterValuesSorterInterface::class);

        return $sorter;
    }

    public function supports(Facet $facet, FacetValues $values): bool
    {
        if ($facet->type !== 'array') {
            return false;
        }

        if (count($values) < 2) {
            return false;
        }

        foreach ($values->getValues() as $value) {
            if (is_numeric($value)) {
                return false;
            }
        }

        return true;
    }
}
