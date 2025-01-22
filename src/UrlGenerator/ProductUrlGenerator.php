<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\UrlGenerator;

use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Webmozart\Assert\Assert;

final class ProductUrlGenerator extends AbstractEntityUrlGenerator
{
    public function __construct(private RouterInterface $router)
    {
        parent::__construct($router);
    }

    public function generate(IndexableInterface $entity, array $context = []): string
    {
        Assert::true($this->supports($entity, $context));

        $params = [
            'slug' => $entity->getTranslation($context['localeCode'])->getSlug(),
        ];

        $route = $this->router->getRouteCollection()->get('sylius_shop_product_show');

        if ($route !== null && $route->hasRequirement('_locale')) {
            $params['_locale'] = $context['localeCode'];
        }

        return $this->router->generate('sylius_shop_product_show', $params);
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
