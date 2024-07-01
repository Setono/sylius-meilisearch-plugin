<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\UrlGenerator;

use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Webmozart\Assert\Assert;

final class TaxonUrlGenerator extends AbstractEntityUrlGenerator
{
    public function generate(IndexableInterface $entity, array $context = []): string
    {
        Assert::true($this->supports($entity, $context));

        return $this->urlGenerator->generate('sylius_shop_product_index', [
            'slug' => $entity->getTranslation($context['localeCode'])->getSlug(),
            '_locale' => $context['localeCode'],
        ]);
    }

    /**
     * @psalm-assert-if-true TaxonInterface $entity
     * @psalm-assert-if-true string $context['localeCode']
     */
    public function supports(IndexableInterface $entity, array $context = []): bool
    {
        return $entity instanceof TaxonInterface && isset($context['localeCode']) && is_string($context['localeCode']);
    }
}
