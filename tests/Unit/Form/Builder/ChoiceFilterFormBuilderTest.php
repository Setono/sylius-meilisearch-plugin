<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\Form\Builder;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\Facet;
use Setono\SyliusMeilisearchPlugin\Engine\FacetValues;
use Setono\SyliusMeilisearchPlugin\Form\Builder\ChoiceFilterFormBuilder;
use Setono\SyliusMeilisearchPlugin\Form\Builder\Sorter\FilterValuesSorterInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * A reversing sorter referenced by its FQCN (not registered as a service) to exercise
 * the backwards-compatible class-string instantiation path.
 */
final class ReversingSorter implements FilterValuesSorterInterface
{
    public function sort(array $values): array
    {
        return array_reverse($values, true);
    }
}

/**
 * @covers \Setono\SyliusMeilisearchPlugin\Form\Builder\ChoiceFilterFormBuilder
 */
final class ChoiceFilterFormBuilderTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @return array<string, string> the choices passed to the form builder
     */
    private function buildChoices(Container $locator, ?string $sorter): array
    {
        $builder = new ChoiceFilterFormBuilder($locator);
        $facet = new Facet('brand', 'array', 0, $sorter);
        $facetValues = new FacetValues('brand', ['a' => 1, 'b' => 1, 'c' => 1]);

        $captured = [];

        $formBuilder = $this->prophesize(FormBuilderInterface::class);
        $formBuilder->add('brand', ChoiceType::class, Argument::type('array'))->will(
            static function (array $args) use (&$captured, $formBuilder): FormBuilderInterface {
                /** @var array{choices: array<string, string>} $options */
                $options = $args[2];
                $captured = $options['choices'];

                return $formBuilder->reveal();
            },
        );

        $builder->build($formBuilder->reveal(), $facet, $facetValues);

        return $captured;
    }

    /**
     * @test
     */
    public function it_resolves_a_sorter_from_the_service_locator_by_id(): void
    {
        $locator = new Container();
        $locator->set('app.reverse_sorter', new ReversingSorter());

        self::assertSame(
            ['c' => 'c', 'b' => 'b', 'a' => 'a'],
            $this->buildChoices($locator, 'app.reverse_sorter'),
        );
    }

    /**
     * @test
     */
    public function it_does_not_sort_when_no_sorter_is_configured(): void
    {
        self::assertSame(
            ['a' => 'a', 'b' => 'b', 'c' => 'c'],
            $this->buildChoices(new Container(), null),
        );
    }

    /**
     * @test
     */
    public function it_throws_for_an_unknown_sorter_reference(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->buildChoices(new Container(), 'neither_a_service_nor_a_class');
    }
}
