<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Form\Builder;

use Setono\SyliusMeilisearchPlugin\Document\Metadata\Metadata;
use Symfony\Component\Form\FormBuilderInterface;

interface SortingFormBuilderInterface
{
    public function build(FormBuilderInterface $searchFormBuilder, Metadata $metadata): void;
}
