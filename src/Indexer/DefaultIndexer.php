<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Indexer;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectRepository;
use Meilisearch\Client;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\Config\IndexableResource;
use Setono\SyliusMeilisearchPlugin\Config\IndexRegistry;
use Setono\SyliusMeilisearchPlugin\DataMapper\DataMapperInterface;
use Setono\SyliusMeilisearchPlugin\Document\Document;
use Setono\SyliusMeilisearchPlugin\Filter\Doctrine\FilterInterface as DoctrineFilterInterface;
use Setono\SyliusMeilisearchPlugin\Filter\Object\FilterInterface as ObjectFilterInterface;
use Setono\SyliusMeilisearchPlugin\IndexScope\IndexScope;
use Setono\SyliusMeilisearchPlugin\Message\Command\IndexEntities;
use Setono\SyliusMeilisearchPlugin\Message\Command\IndexResource;
use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScopeProviderInterface;
use Setono\SyliusMeilisearchPlugin\Provider\IndexSettings\IndexSettingsProviderInterface;
use Setono\SyliusMeilisearchPlugin\Repository\IndexableResourceRepositoryInterface;
use Setono\SyliusMeilisearchPlugin\Resolver\IndexName\IndexNameResolverInterface;
use Setono\SyliusMeilisearchPlugin\Settings\IndexSettings;
use Setono\SyliusMeilisearchPlugin\Settings\SortableReplica;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Webmozart\Assert\Assert;

/**
 * NOT final as this makes it easier to override and extend this indexer
 */
class DefaultIndexer extends AbstractIndexer
{
    use ORMTrait;

    /**
     * @param list<string> $normalizationGroups
     */
    public function __construct(
        ManagerRegistry $managerRegistry,
        protected readonly IndexScopeProviderInterface $indexScopeProvider,
        protected readonly IndexNameResolverInterface $indexNameResolver,
        protected readonly IndexSettingsProviderInterface $indexSettingsProvider,
        protected readonly DataMapperInterface $dataMapper,
        protected readonly MessageBusInterface $commandBus,
        protected readonly NormalizerInterface $normalizer,
        protected readonly Client $client,
        protected readonly IndexRegistry $indexRegistry,
        protected readonly DoctrineFilterInterface $doctrineFilter,
        protected readonly ObjectFilterInterface $objectFilter,
        protected readonly array $normalizationGroups = ['setono:sylius-meilisearch:document'],
    ) {
        $this->managerRegistry = $managerRegistry;
    }

    public function index(Index|string $index): void
    {
        if (is_string($index)) {
            $index = $this->indexRegistry->get($index);
        }

        foreach ($index->resources as $resource) {
            $this->commandBus->dispatch(new IndexResource($index, $resource));
        }
    }

    public function indexResource(Index|string $index, string $resource): void
    {
        if (is_string($index)) {
            $index = $this->indexRegistry->get($index);
        }

        $indexableResource = $index->getResource($resource);

        foreach ($this->getIdBatches($indexableResource) as $ids) {
            $this->commandBus->dispatch(new IndexEntities($indexableResource, $ids));
        }
    }

    public function indexEntitiesWithIds(array $ids, string $type): void
    {
        if ([] === $ids) {
            return;
        }

        $index = $this->indexRegistry->getByResource($type);

        foreach ($this->indexScopeProvider->getAll($index) as $indexScope) {
            $algoliaIndex = $this->prepareIndex(
                $this->indexNameResolver->resolveFromIndexScope($indexScope),
                $this->indexSettingsProvider->getSettings($indexScope),
            );

            foreach ($this->getObjects($ids, $type, $indexScope) as $obj) {
                $doc = new $index->document();
                $this->dataMapper->map($obj, $doc, $indexScope);

                $this->objectFilter->filter($obj, $doc, $indexScope);

                $data = $this->normalize($doc);

                $algoliaIndex->saveObject($data);
            }
        }
    }

    public function removeEntitiesWithIds(array $ids, string $type): void
    {
        if ([] === $ids) {
            return;
        }

        $index = $this->indexRegistry->getByResource($type);

        foreach ($this->indexScopeProvider->getAll($index) as $indexScope) {
            $algoliaIndex = $this->prepareIndex(
                $this->indexNameResolver->resolveFromIndexScope($indexScope),
                $this->indexSettingsProvider->getSettings($indexScope),
            );

            foreach ($this->getObjects($ids, $type, $indexScope) as $obj) {
                $algoliaIndex->deleteObject($obj->getObjectId());
            }
        }
    }

    /**
     * @return \Generator<int, non-empty-list<mixed>>
     */
    protected function getIdBatches(IndexableResource $resource): \Generator
    {
        $manager = $this->getManager($resource->class);

        /** @var IndexableResourceRepositoryInterface|ObjectRepository $repository */
        $repository = $manager->getRepository($resource->class);
        Assert::isInstanceOf($repository, IndexableResourceRepositoryInterface::class, sprintf(
            'The repository for resource "%s" must implement the interface %s',
            $resource->name,
            IndexableResourceRepositoryInterface::class,
        ));

        $firstResult = 0;
        $maxResults = 100;

        $qb = $repository->createIndexableCollectionQueryBuilder();
        $qb->select(sprintf('%s.id', $qb->getRootAliases()[0]));
        $qb->setMaxResults($maxResults);

        $this->doctrineFilter->apply($qb, $resource);

        while (true) {
            $qb->setFirstResult($firstResult);

            $ids = $qb->getQuery()->getResult();
            Assert::isArray($ids);

            $ids = array_values(array_map(/** @return mixed */static function (array $elm) {
                Assert::keyExists($elm, 'id');

                return $elm['id'];
            }, $ids));

            if ([] === $ids) {
                break;
            }

            yield $ids;

            $firstResult += $maxResults;

            $manager->clear();
        }
    }

    /**
     * @param list<mixed> $ids
     * @param class-string<IndexableInterface> $type
     *
     * @return list<IndexableInterface>
     */
    protected function getObjects(array $ids, string $type, IndexScope $indexScope): array
    {
        $manager = $this->getManager($type);

        /** @var IndexableResourceRepositoryInterface|ObjectRepository $repository */
        $repository = $manager->getRepository($type);
        Assert::isInstanceOf($repository, IndexableResourceRepositoryInterface::class, sprintf(
            'The repository for resource "%s" must implement the interface %s',
            $type,
            IndexableResourceRepositoryInterface::class,
        ));

        return $repository->findFromIndexScopeAndIds($indexScope, $ids);
    }

    protected function normalize(Document $document): array
    {
        $res = $this->normalizer->normalize($document, null, [
            'groups' => $this->normalizationGroups,
        ]);
        Assert::isArray($res);

        return $res;
    }

    protected function prepareIndex(string $indexName, IndexSettings $indexSettings): SearchIndex
    {
        $index = $this->client->initIndex($indexName);
        Assert::isInstanceOf($index, SearchIndex::class);

        // if the index already exists we don't want to override any settings. TODO why don't we want that? Should we make a command that resets settings to application defaults? We also need to take into account the forwardToReplicas option below
        if ($index->exists()) {
            return $index;
        }

        /**
         * this first call will create the index (including any replica indexes)
         *
         * @psalm-suppress MixedMethodCall
         */
        $index->setSettings($indexSettings->toArray())->wait();

        /** @psalm-suppress MixedAssignment,MixedArrayAccess */
        $indexSettings->ranking = $index->getSettings()['ranking'];

        foreach ($indexSettings->replicas as $replica) {
            if (!$replica instanceof SortableReplica) {
                continue;
            }

            $replicaIndex = $this->client->initIndex($replica->name);
            Assert::isInstanceOf($replicaIndex, SearchIndex::class);

            $replicaIndexSettings = clone $indexSettings;
            $replicaIndexSettings->replicas = [];
            array_unshift($replicaIndexSettings->ranking, $replica->ranking());

            $replicaIndex->setSettings($replicaIndexSettings->toArray());
        }

        return $index;
    }
}
