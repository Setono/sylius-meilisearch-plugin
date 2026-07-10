<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Command;

use Meilisearch\Client;
use Meilisearch\Contracts\TasksQuery;
use Setono\SyliusMeilisearchPlugin\Config\IndexRegistryInterface;
use Setono\SyliusMeilisearchPlugin\Message\Command\Index;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScopeProviderInterface;
use Setono\SyliusMeilisearchPlugin\Resolver\IndexUid\IndexUidResolverInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'setono:sylius-meilisearch:index',
    description: 'Will index all configured indexes',
)]
final class IndexCommand extends Command
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly IndexRegistryInterface $indexRegistry,
        private readonly Client $client,
        private readonly IndexScopeProviderInterface $indexScopeProvider,
        private readonly IndexUidResolverInterface $indexUidResolver,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'indexes',
                InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
                'Names of the index(es) to index',
                $this->indexRegistry->getNames(),
            )
            ->addOption('wait', 'w', InputOption::VALUE_NONE, 'Wait for the indexing to finish')
            ->addOption('wait-timeout', 't', InputOption::VALUE_REQUIRED, 'The maximum time to wait for the indexing to finish in seconds. This is only relevant if you have enabled the "wait" option', 300)
            ->addOption('delete', 'd', InputOption::VALUE_NONE, 'Delete index before creating')
        ;
    }

    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        /** @var list<string> $indexes */
        $indexes = $input->getArgument('indexes');

        foreach ($indexes as $index) {
            if (!$this->indexRegistry->has($index)) {
                throw new RuntimeException(sprintf(
                    'No index exists with the name "%s". Available indexes are: [%s]',
                    $index,
                    implode(', ', $this->indexRegistry->getNames()),
                ));
            }
        }

        $waitTimeout = $input->getOption('wait-timeout');

        if (!is_numeric($waitTimeout)) {
            throw new RuntimeException('The wait-timeout option must be a number');
        }

        $waitTimeout = (int) $waitTimeout;

        if ($waitTimeout <= 0) {
            throw new RuntimeException('The wait-timeout option must be greater than 0');
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var list<string> $indexes */
        $indexes = $input->getArgument('indexes');
        $delete = $input->getOption('delete');

        if ($delete) {
            $output->writeln('<comment>WARNING: The delete option is enabled</comment>');
        }

        foreach ($indexes as $index) {
            $output->writeln(sprintf('Index "%s" submitted for indexing', $index));

            $this->commandBus->dispatch(new Index($index, $delete));
        }

        /** @var bool $wait */
        $wait = $input->getOption('wait');

        if ($wait) {
            $this->wait($this->resolveIndexUids($indexes), (int) $input->getOption('wait-timeout'), $output);
        }

        return 0;
    }

    /**
     * Resolves the concrete Meilisearch index uids (across all scopes) for the given index names,
     * so --wait only waits on tasks belonging to this run rather than every task on the instance.
     *
     * @param list<string> $indexes
     *
     * @return list<string>
     */
    private function resolveIndexUids(array $indexes): array
    {
        $uids = [];

        foreach ($indexes as $index) {
            foreach ($this->indexScopeProvider->getAll($this->indexRegistry->get($index)) as $indexScope) {
                $uid = $this->indexUidResolver->resolveFromIndexScope($indexScope);
                $uids[$uid] = $uid;
            }
        }

        return array_values($uids);
    }

    /**
     * @param list<string> $indexUids
     */
    private function wait(array $indexUids, int $waitTimeout, OutputInterface $output): void
    {
        $start = time();

        $query = self::createTasksQuery($indexUids);

        do {
            $results = $this->client->getTasks($query);

            if ($results->getTotal() === 0) {
                return;
            }

            $output->writeln(sprintf('Waiting for %d tasks to finish...', $results->getTotal()));

            sleep(10);
        } while ((time() - $start) < $waitTimeout);

        throw new RuntimeException(sprintf('The indexing did not finish within the specified time (%d seconds)', $waitTimeout));
    }

    /**
     * @param list<string> $indexUids
     */
    public static function createTasksQuery(array $indexUids): TasksQuery
    {
        $query = new TasksQuery();
        $query->setStatuses(['enqueued', 'processing']);

        // Scope the query to this run's index uids so we don't wait on unrelated tasks from
        // other developers/environments sharing the same Meilisearch instance (the exact
        // scenario MEILISEARCH_PREFIX exists for).
        if ([] !== $indexUids) {
            $query->setIndexUids($indexUids);
        }

        return $query;
    }
}
