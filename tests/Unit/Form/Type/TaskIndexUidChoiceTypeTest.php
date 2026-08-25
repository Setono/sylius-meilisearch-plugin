<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\Form\Type;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusMeilisearchPlugin\Form\Type\TaskIndexUidChoiceType;
use Setono\SyliusMeilisearchPlugin\Provider\IndexUids\IndexUidsProviderInterface;
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
    public function it_offers_the_concrete_index_uids_as_choices(): void
    {
        $indexUidsProvider = $this->prophesize(IndexUidsProviderInterface::class);
        $indexUidsProvider->getFlattened()->willReturn(['products__fashion_web__en_us__usd', 'taxons__fashion_web__en_us']);

        self::assertSame(
            ['products__fashion_web__en_us__usd', 'taxons__fashion_web__en_us'],
            $this->values($indexUidsProvider->reveal()),
        );
    }

    /**
     * @test
     */
    public function it_offers_no_choices_when_the_uids_cannot_be_resolved(): void
    {
        $indexUidsProvider = $this->prophesize(IndexUidsProviderInterface::class);
        $indexUidsProvider->getFlattened()->willThrow(new \RuntimeException('Database is down'));

        self::assertSame([], $this->values($indexUidsProvider->reveal()));
    }

    /**
     * @return list<string>
     */
    private function values(IndexUidsProviderInterface $indexUidsProvider): array
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

        $values = [];
        foreach ($vars['choices'] as $choice) {
            self::assertInstanceOf(ChoiceView::class, $choice);
            self::assertIsString($choice->value);
            $values[] = $choice->value;
        }

        return $values;
    }
}
