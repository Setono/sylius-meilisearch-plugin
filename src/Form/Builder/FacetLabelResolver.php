<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Form\Builder;

use Setono\SyliusMeilisearchPlugin\Document\Metadata\Facet;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Component\Product\Model\ProductAttributeInterface;
use Sylius\Component\Product\Model\ProductOptionInterface;
use Sylius\Component\Product\Model\ProductOptionTranslationInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use function Symfony\Component\String\u;
use Symfony\Contracts\Translation\TranslatorInterface;

final class FacetLabelResolver implements FacetLabelResolverInterface
{
    /**
     * @param RepositoryInterface<ProductAttributeInterface> $productAttributeRepository
     * @param RepositoryInterface<ProductOptionInterface> $productOptionRepository
     */
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly RepositoryInterface $productAttributeRepository,
        private readonly RepositoryInterface $productOptionRepository,
        private readonly LocaleContextInterface $localeContext,
    ) {
    }

    /**
     * Resolves the label for a facet in this order:
     *
     * 1. The translation key when the catalogue defines it, so projects can override any facet label
     * 2. For dynamic fields (attr_* / opt_*), the translated name of the Sylius product attribute/option
     * 3. A humanized version of the facet name, so facets without a shipped translation (e.g. "brand")
     *    don't render their raw translation key
     */
    public function resolve(Facet $facet): string
    {
        $name = u($facet->name)->snake()->toString();
        $key = sprintf('setono_sylius_meilisearch.form.search.facet.%s', $name);

        // when the translator returns the key unchanged there is no translation for it
        if ($this->translator->trans($key) !== $key) {
            return $key;
        }

        $subjectName = $this->resolveSubjectName($facet->name);
        if (null !== $subjectName) {
            return $subjectName;
        }

        return ucfirst(str_replace('_', ' ', $name));
    }

    private function resolveSubjectName(string $facetName): ?string
    {
        if (str_starts_with($facetName, 'attr_')) {
            $attribute = $this->productAttributeRepository->findOneBy(['code' => substr($facetName, 5)]);
            if (!$attribute instanceof ProductAttributeInterface) {
                return null;
            }

            $name = $attribute->getNameByLocaleCode($this->localeContext->getLocaleCode());

            return '' === $name ? null : $name;
        }

        if (str_starts_with($facetName, 'opt_')) {
            $option = $this->productOptionRepository->findOneBy(['code' => substr($facetName, 4)]);
            if (!$option instanceof ProductOptionInterface) {
                return null;
            }

            $translation = $option->getTranslation($this->localeContext->getLocaleCode());
            if (!$translation instanceof ProductOptionTranslationInterface) {
                return null;
            }

            $name = $translation->getName();

            return null === $name || '' === $name ? null : $name;
        }

        return null;
    }
}
