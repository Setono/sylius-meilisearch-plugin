<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Document;

use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScope;
use Setono\SyliusMeilisearchPlugin\Settings\IndexSettings;

/**
 * All documents should extend this class
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
     * todo do we need this?
     *
     * This is the FQCN for the document being sent to Meilisearch. This makes it a lot easier to deserialize the JSON
     * when it comes back from Meilisearch since we know which class to deserialize to
     *
     * @var class-string<Document>
     */
    public string $documentClass;

    /**
     * Making the constructor final allows us to always be able toinstantiate an extending class without worrying about constructor arguments
     */
    final public function __construct()
    {
        $this->documentClass = static::class;
    }

    /**
     * MUST return an array indexed by the attribute name and the sort order as the value, e.g.
     *
     * [
     *   'price' => 'asc',
     *   'createdAt => 'desc'
     * ]
     *
     * NOTE that this is not applied to the customRanking setting, but is used to create replica indexes where the
     * ranking setting will match your sorting. The above example would result in two replica indexes with
     * ranking as asc(price) and desc(createdAt) respectively
     *
     * @return array<string, string>
     */
    public static function getSortableAttributes(): array
    {
        return [];
    }

    public static function getDefaultSettings(IndexScope $indexScope): IndexSettings
    {
        $settings = new IndexSettings();

        if (null !== $indexScope->localeCode) {
            $language = substr($indexScope->localeCode, 0, 2);
            $settings->queryLanguages = [$language];
            $settings->indexLanguages = [$language];
        }

        // 60 is a very good number because it is dividable by 6, 5, 4, 3, and 2 which means that your responsive views
        // are going to look good no matter how many products you show per row (with a max of 6 per row though ;))
        $settings->hitsPerPage = 60;

        return $settings;
    }
}
