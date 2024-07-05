<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Normalizer;

use Setono\SyliusMeilisearchPlugin\Settings\Settings;
use Symfony\Component\Serializer\Exception\LogicException;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class SettingsNormalizer implements NormalizerInterface
{
    public function __construct(private readonly NormalizerInterface $normalizer)
    {
    }

    public function normalize(mixed $object, ?string $format = null, array $context = []): array
    {
        if (!$this->supportsNormalization($object)) {
            throw new LogicException(sprintf('The object must be an instance of %s', Settings::class));
        }

        $data = $this->normalizer->normalize($object, $format, $context);
        if ($data instanceof \ArrayObject) {
            $data = $data->getArrayCopy();
        }

        if (!is_array($data)) {
            throw new LogicException('The normalized settings data must be an array or an ArrayObject');
        }

        return array_filter($data, static function (mixed $value): bool {
            if (is_array($value)) {
                return [] !== $value;
            }

            return null !== $value;
        });
    }

    /**
     * @psalm-assert-if-true Settings $data
     */
    public function supportsNormalization(mixed $data, ?string $format = null): bool
    {
        return $data instanceof Settings;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            Settings::class => true,
        ];
    }
}
