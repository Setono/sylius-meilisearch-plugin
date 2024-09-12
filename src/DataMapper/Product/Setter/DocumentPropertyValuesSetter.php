<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\DataMapper\Product\Setter;

use Setono\SyliusMeilisearchPlugin\DataMapper\Product\Formatter\TargetPropertyValuesFormatterInterface;
use Setono\SyliusMeilisearchPlugin\DataMapper\Product\Provider\ReflectionAttributeValuesProviderInterface;
use Setono\SyliusMeilisearchPlugin\Document\Document;

final class DocumentPropertyValuesSetter implements DocumentPropertyValuesSetterInterface
{
    public function __construct(
        private readonly ReflectionAttributeValuesProviderInterface $reflectionAttributeValuesProvider,
        private readonly TargetPropertyValuesFormatterInterface $targetPropertyValuesFormatter,
    ) {
    }

    public function setFor(Document $target, array $attributes): void
    {
        $documentReflection = new \ReflectionClass($target);
        foreach ($documentReflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $reflectionProperty) {
            $propertyName = $reflectionProperty->getName();

            foreach ($reflectionProperty->getAttributes() as $reflectionAttribute) {
                try {
                    $values = $this->reflectionAttributeValuesProvider->provide(
                        $reflectionAttribute,
                        $target,
                        $propertyName,
                        $attributes,
                    );
                } catch (\InvalidArgumentException) {
                    continue;
                }

                $values = $this->targetPropertyValuesFormatter->format($values);

                /** @psalm-suppress MixedArgument */
                $target->{$propertyName} = array_merge($target->{$propertyName}, $values);
            }
        }
    }
}
