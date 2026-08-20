<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Normalizer;

use Setono\SyliusMeilisearchPlugin\Document\Document;
use Symfony\Component\Serializer\Exception\LogicException;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Flattens the document's $attributes bag to top level fields so a dynamic field like "attr_color"
 * becomes its own field in Meilisearch (nested field names contain dots, which the search form cannot handle)
 */
final class DocumentNormalizer implements NormalizerInterface
{
    public function __construct(private readonly NormalizerInterface $normalizer)
    {
    }

    public function normalize(mixed $object, ?string $format = null, array $context = []): array
    {
        if (!$this->supportsNormalization($object)) {
            throw new LogicException(sprintf('The object must be an instance of %s', Document::class));
        }

        $data = $this->normalizer->normalize($object, $format, $context);
        if ($data instanceof \ArrayObject) {
            $data = $data->getArrayCopy();
        }

        if (!is_array($data)) {
            throw new LogicException('The normalized document data must be an array or an ArrayObject');
        }

        /** @var mixed $attributes */
        $attributes = $data['attributes'] ?? [];
        unset($data['attributes']);

        if (is_array($attributes)) {
            foreach ($attributes as $name => $value) {
                // an existing key always wins so a dynamic field can never overwrite a declared document field
                if (!array_key_exists($name, $data)) {
                    /** @psalm-suppress MixedAssignment */
                    $data[$name] = $value;
                }
            }
        }

        return $data;
    }

    /**
     * @psalm-assert-if-true Document $data
     */
    public function supportsNormalization(mixed $data, ?string $format = null): bool
    {
        return $data instanceof Document;
    }

    /**
     * @return array<class-string, bool>
     */
    public function getSupportedTypes(?string $format): array
    {
        return [
            Document::class => true,
        ];
    }
}
