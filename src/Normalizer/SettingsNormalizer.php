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

        // Only strip null values. Empty arrays are meaningful for list-valued settings
        // (filterableAttributes, sortableAttributes, stopWords, synonyms, …): because
        // updateSettings is a *partial* update, sending [] is exactly how you reset such a
        // setting. Keys that must be omitted when "not configured" are modelled as null in
        // Settings, not as [].
        return array_filter($data, static fn (mixed $value): bool => null !== $value);
    }

    /**
     * @psalm-assert-if-true Settings $data
     */
    public function supportsNormalization(mixed $data, ?string $format = null): bool
    {
        return $data instanceof Settings;
    }

    /**
     * @return array<class-string, bool>
     */
    public function getSupportedTypes(?string $format): array
    {
        return [
            Settings::class => true,
        ];
    }
}
