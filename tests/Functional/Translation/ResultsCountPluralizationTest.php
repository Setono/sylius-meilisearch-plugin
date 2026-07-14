<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Functional\Translation;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ResultsCountPluralizationTest extends KernelTestCase
{
    /**
     * @test
     */
    public function it_pluralizes_the_results_count(): void
    {
        self::bootKernel(['environment' => 'test', 'debug' => true]);

        /** @var TranslatorInterface $translator */
        $translator = self::getContainer()->get('translator');

        $key = 'setono_sylius_meilisearch.form.search.results_count';

        self::assertSame('0 results', $translator->trans($key, ['%count%' => 0], null, 'en'));
        self::assertSame('1 result', $translator->trans($key, ['%count%' => 1], null, 'en'));
        self::assertSame('8 results', $translator->trans($key, ['%count%' => 8], null, 'en'));
    }
}
