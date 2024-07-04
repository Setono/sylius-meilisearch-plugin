<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Normalizer;

use Setono\SyliusMeilisearchPlugin\Document\Product;
use Symfony\Component\Serializer\Exception\LogicException;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class ProductNormalizer implements NormalizerInterface
{
    public function __construct(private readonly NormalizerInterface $normalizer)
    {
    }

    public function normalize(mixed $object, ?string $format = null, array $context = []): array
    {
        if (!$this->supportsNormalization($object)) {
            throw new LogicException(sprintf('The object must be an instance of %s', Product::class));
        }

        $data = $this->normalizer->normalize($object, $format, $context);
        if ($data instanceof \ArrayObject) {
            $data = $data->getArrayCopy();
        }

        if (!is_array($data)) {
            throw new LogicException('The normalized product data must be an array or an ArrayObject');
        }

        /**
         * @var string $option
         * @var list<string> $values
         */
        foreach ($data['options'] as $option => $values) {
            $data[$option . '_option'] = $values;
        }

        unset($data['options']);

        return $data;
    }

    /**
     * @psalm-assert-if-true Product $data
     */
    public function supportsNormalization(mixed $data, ?string $format = null): bool
    {
        return $data instanceof Product;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            Product::class => true,
        ];
    }
}
