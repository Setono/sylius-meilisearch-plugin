<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\Form\Type;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusMeilisearchPlugin\Form\Type\TaskIndexUidChoiceType;
use Setono\SyliusMeilisearchPlugin\Provider\IndexUids\IndexUidsProviderInterface;
use Symfony\Component\Form\ChoiceList\View\ChoiceGroupView;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\PreloadedExtension;

/**
 * @covers \Setono\SyliusMeilisearchPlugin\Form\Type\TaskIndexUidChoiceType
 */
final class TaskIndexUidChoiceTypeTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_offers_the_concrete_index_uids_grouped_per_index(): void
    {
        $indexUidsProvider = $this->prophesize(IndexUidsProviderInterface::class);
        $indexUidsProvider->getAll()->willReturn([
            'products' => ['products__fashion_web__en_us__usd', 'products__fashion_web__da_dk__dkk'],
            'taxons' => ['taxons__fashion_web__en_us'],
            'empty' => [],
        ]);

        self::assertSame([
            'Products' => ['products__fashion_web__en_us__usd', 'products__fashion_web__da_dk__dkk'],
            'Taxons' => ['taxons__fashion_web__en_us'],
        ], $this->groupedValues($indexUidsProvider->reveal()));
    }

    /**
     * @test
     */
    public function it_offers_no_choices_when_the_uids_cannot_be_resolved(): void
    {
        $indexUidsProvider = $this->prophesize(IndexUidsProviderInterface::class);
        $indexUidsProvider->getAll()->willThrow(new \RuntimeException('Database is down'));

        self::assertSame([], $this->groupedValues($indexUidsProvider->reveal()));
    }

    /**
     * @return array<string, list<string>>
     */
    private function groupedValues(IndexUidsProviderInterface $indexUidsProvider): array
    {
        $factory = Forms::createFormFactoryBuilder()
            ->addExtensions([new PreloadedExtension([new TaskIndexUidChoiceType($indexUidsProvider)], [])])
            ->getFormFactory()
        ;

        $view = $factory->create(TaskIndexUidChoiceType::class)->createView();

        $vars = $view->vars;
        self::assertIsArray($vars);
        self::assertArrayHasKey('choices', $vars);
        self::assertIsArray($vars['choices']);

        $grouped = [];
        foreach ($vars['choices'] as $group) {
            self::assertInstanceOf(ChoiceGroupView::class, $group);
            self::assertIsString($group->label);
            self::assertIsArray($group->choices);

            $values = [];
            foreach ($group->choices as $choice) {
                self::assertInstanceOf(ChoiceView::class, $choice);
                self::assertIsString($choice->value);
                $values[] = $choice->value;
            }

            $grouped[$group->label] = $values;
        }

        return $grouped;
    }
}
