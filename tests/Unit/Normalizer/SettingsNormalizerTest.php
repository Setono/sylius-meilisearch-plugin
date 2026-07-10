<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\Normalizer;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusMeilisearchPlugin\Normalizer\SettingsNormalizer;
use Setono\SyliusMeilisearchPlugin\Settings\Settings;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @covers \Setono\SyliusMeilisearchPlugin\Normalizer\SettingsNormalizer
 */
final class SettingsNormalizerTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_keeps_empty_arrays_so_list_settings_can_be_reset(): void
    {
        $settings = new Settings();

        $inner = $this->prophesize(NormalizerInterface::class);
        $inner->normalize($settings, null, [])->willReturn([
            'searchableAttributes' => ['*'],
            'filterableAttributes' => [],
            'sortableAttributes' => [],
            'stopWords' => [],
            'synonyms' => [],
            'distinctAttribute' => null,
        ]);

        $normalizer = new SettingsNormalizer($inner->reveal());

        $result = $normalizer->normalize($settings);

        // empty list-valued settings are preserved (sending [] resets a partial-update setting)
        self::assertArrayHasKey('filterableAttributes', $result);
        self::assertSame([], $result['filterableAttributes']);
        self::assertSame([], $result['sortableAttributes']);
        self::assertSame([], $result['stopWords']);

        // synonyms is a map, not a list: empty must be an object so Meilisearch accepts it as {} (not [])
        self::assertInstanceOf(\stdClass::class, $result['synonyms']);

        // null values are still stripped
        self::assertArrayNotHasKey('distinctAttribute', $result);
    }

    /**
     * @test
     */
    public function it_keeps_a_populated_synonyms_map_as_is(): void
    {
        $settings = new Settings();

        $inner = $this->prophesize(NormalizerInterface::class);
        $inner->normalize($settings, null, [])->willReturn([
            'synonyms' => ['sneakers' => ['trainers']],
        ]);

        $result = (new SettingsNormalizer($inner->reveal()))->normalize($settings);

        self::assertSame(['sneakers' => ['trainers']], $result['synonyms']);
    }
}
