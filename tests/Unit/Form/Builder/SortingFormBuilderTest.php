<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\Form\Builder;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusMeilisearchPlugin\Document\Attribute\Sortable as SortableAttribute;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\MetadataInterface;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\Sortable;
use Setono\SyliusMeilisearchPlugin\Engine\SearchRequest;
use Setono\SyliusMeilisearchPlugin\Form\Builder\SortingFormBuilder;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;

final class SortingFormBuilderTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_builds(): void
    {
        $searchFormBuilder = $this->prophesize(FormBuilderInterface::class);
        $searchFormBuilder->add(SearchRequest::QUERY_PARAMETER_SORT, ChoiceType::class, [
            'choices' => [
                'setono_sylius_meilisearch.form.search.sorting.name.asc' => 'name:asc',
                'setono_sylius_meilisearch.form.search.sorting.name.desc' => 'name:desc',
                'setono_sylius_meilisearch.form.search.sorting.price.desc' => 'price:desc',
            ],
            'required' => false,
            'placeholder' => 'setono_sylius_meilisearch.form.search.sorting.placeholder',
        ])->willReturn($searchFormBuilder)->shouldBeCalledOnce();

        $metadata = $this->prophesize(MetadataInterface::class);
        $metadata->getSortableAttributes()->willReturn([
            new Sortable('name'),
            new Sortable('price', SortableAttribute::DESC),
        ]);

        $builder = new SortingFormBuilder();
        $builder->build($searchFormBuilder->reveal(), $metadata->reveal());
    }
}
