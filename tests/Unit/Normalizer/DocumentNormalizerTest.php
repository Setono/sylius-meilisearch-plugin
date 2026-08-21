<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\Normalizer;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusMeilisearchPlugin\Document\Product as ProductDocument;
use Setono\SyliusMeilisearchPlugin\Normalizer\DocumentNormalizer;
use Symfony\Component\Serializer\Exception\LogicException;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @covers \Setono\SyliusMeilisearchPlugin\Normalizer\DocumentNormalizer
 */
final class DocumentNormalizerTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @param array<string, mixed> $innerResult
     */
    private function normalizer(object $document, array $innerResult): DocumentNormalizer
    {
        $inner = $this->prophesize(NormalizerInterface::class);
        $inner->normalize($document, null, [])->willReturn($innerResult);

        return new DocumentNormalizer($inner->reveal());
    }

    /**
     * @test
     */
    public function it_flattens_the_attributes_bag_to_top_level_fields(): void
    {
        $document = new ProductDocument();

        $data = $this->normalizer($document, [
            'name' => 'T-shirt',
            'dynamicFields' => [
                'attr_color' => ['Red', 'Blue'],
                'attr_eco_friendly' => true,
            ],
        ])->normalize($document);

        self::assertSame([
            'name' => 'T-shirt',
            'attr_color' => ['Red', 'Blue'],
            'attr_eco_friendly' => true,
        ], $data);
    }

    /**
     * @test
     */
    public function it_never_overwrites_an_existing_field(): void
    {
        $document = new ProductDocument();

        $data = $this->normalizer($document, [
            'name' => 'T-shirt',
            'dynamicFields' => [
                'name' => 'evil',
            ],
        ])->normalize($document);

        self::assertSame(['name' => 'T-shirt'], $data);
    }

    /**
     * @test
     */
    public function it_removes_the_bag_when_it_is_empty(): void
    {
        $document = new ProductDocument();

        $data = $this->normalizer($document, [
            'name' => 'T-shirt',
            'dynamicFields' => [],
        ])->normalize($document);

        self::assertSame(['name' => 'T-shirt'], $data);
    }

    /**
     * @test
     */
    public function it_only_supports_documents(): void
    {
        $inner = $this->prophesize(NormalizerInterface::class);
        $normalizer = new DocumentNormalizer($inner->reveal());

        $this->expectException(LogicException::class);
        $normalizer->normalize(new \stdClass());
    }
}
