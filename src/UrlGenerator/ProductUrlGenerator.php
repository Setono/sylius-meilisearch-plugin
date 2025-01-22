<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\UrlGenerator;

use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Webmozart\Assert\Assert;

final class ProductUrlGenerator extends AbstractEntityUrlGenerator
{
    public function generate(IndexableInterface $entity, array $context = []): string
    {
        Assert::true($this->supports($entity, $context));

        $parameters = [
            'slug' => $entity->getTranslation($context['localeCode'])->getSlug(),
        ];

        $route = $this->router->getRouteCollection()->get('sylius_shop_product_show');

        if (null !== $route && $route->hasRequirement('_locale')) {
            $parameters['_locale'] = $context['localeCode'];
        }

        return $this->router->generate('sylius_shop_product_show', $parameters);
    }

    /**
     * @psalm-assert-if-true ProductInterface $entity
     * @psalm-assert-if-true string $context['localeCode']
     */
    public function supports(IndexableInterface $entity, array $context = []): bool
    {
        return $entity instanceof ProductInterface && isset($context['localeCode']) && is_string($context['localeCode']);
    }
}
