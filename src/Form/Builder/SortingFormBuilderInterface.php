<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Form\Builder;

use Setono\SyliusMeilisearchPlugin\Document\Metadata\MetadataInterface;
use Symfony\Component\Form\FormBuilderInterface;

interface SortingFormBuilderInterface
{
    public function build(FormBuilderInterface $searchFormBuilder, MetadataInterface $metadata): void;
}
