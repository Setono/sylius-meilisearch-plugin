<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Indexer;

use Doctrine\Persistence\ManagerRegistry;
use DoctrineBatchUtils\BatchProcessing\SelectBatchIteratorAggregate;
use Meilisearch\Client;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\DataMapper\DataMapperInterface;
use Setono\SyliusMeilisearchPlugin\Document\Document;
use Setono\SyliusMeilisearchPlugin\Filter\Object\FilterInterface as ObjectFilterInterface;
use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScopeProviderInterface;
use Setono\SyliusMeilisearchPlugin\Resolver\IndexName\IndexNameResolverInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Webmozart\Assert\Assert;

/**
 * NOT final as this makes it easier to override and extend this indexer
 */
class DefaultIndexer extends AbstractIndexer
{
    use ORMTrait;

    public function __construct(
        protected readonly Index $index,
        ManagerRegistry $managerRegistry,
        protected readonly IndexScopeProviderInterface $indexScopeProvider,
        protected readonly IndexNameResolverInterface $indexNameResolver,
        protected readonly DataMapperInterface $dataMapper,
        protected readonly NormalizerInterface $normalizer,
        protected readonly Client $client,
        protected readonly ObjectFilterInterface $objectFilter,
    ) {
        $this->managerRegistry = $managerRegistry;
    }

    public function index(): void
    {
        foreach ($this->index->entities as $entity) {
            $this->indexEntityClass($entity);
        }
    }

    public function indexEntities(array $entities): void
    {
        if ([] === $entities) {
            return;
        }

        foreach ($this->indexScopeProvider->getAll($this->index) as $indexScope) {
            $documents = [];

            foreach ($entities as $entity) {
                $document = new $this->index->document();
                $this->dataMapper->map($entity, $document, $indexScope);

                $this->objectFilter->filter($entity, $document, $indexScope);

                $documents[] = $this->normalize($document);
            }

            $this->client->index($this->indexNameResolver->resolveFromIndexScope($indexScope))->addDocuments($documents, 'id');
        }
    }

    public function removeEntities(array $entities): void
    {
        if ([] === $entities) {
            return;
        }

        foreach ($this->indexScopeProvider->getAll($this->index) as $indexScope) {
            foreach ($entities as $entity) {
                $this->client->index($this->indexNameResolver->resolveFromIndexScope($indexScope))->deleteDocument($entity->getDocumentIdentifier());
            }
        }
    }

    /**
     * @param class-string<IndexableInterface> $entity
     */
    protected function indexEntityClass(string $entity): void
    {
        $q = $this
            ->getManager($entity)
            ->createQueryBuilder()
            ->select('o')
            ->from($entity, 'o')
            ->getQuery()
        ;

        /** @var SelectBatchIteratorAggregate<array-key, IndexableInterface> $objects */
        $objects = SelectBatchIteratorAggregate::fromQuery($q, 100);

        foreach ($objects as $object) {
            $this->indexEntity($object);
        }
    }

    // todo move this to a service
    protected function normalize(Document $document): array
    {
        $data = $this->normalizer->normalize($document);
        Assert::isArray($data);

        return $data;
    }
}
