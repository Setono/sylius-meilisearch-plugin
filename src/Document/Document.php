<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Document;

use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;

/**
 * ALL documents MUST extend this class
 */
abstract class Document
{
    /**
     * This will be the id in Meilisearch. This MUST be unique across the index, therefore if you mix
     * products and taxons for example, use a prefix on the object id
     */
    public ?string $id = null;

    /**
     * This is the id in the database.
     * Used together with the $entityClass, you can identify a given entity in your own database
     */
    public ?string $entityId = null;

    /**
     * This is the entity class FQCN
     *
     * @var class-string<IndexableInterface>|null
     */
    public ?string $entityClass = null;

    /**
     * Making the constructor final allows us to always be able to instantiate an extending class without worrying about constructor arguments
     */
    final public function __construct()
    {
    }
}
