<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Command;

use Meilisearch\Client;
use Meilisearch\Contracts\TasksQuery;
use Setono\SyliusMeilisearchPlugin\Config\IndexRegistryInterface;
use Setono\SyliusMeilisearchPlugin\Message\Command\Index;
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

        /** @var bool $wait */
        $wait = $input->getOption('wait');

        /** @var mixed|null $waitTimeout */
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

        foreach ($indexes as $index) {
            $output->writeln(sprintf('Index "%s" submitted for indexing', $index));

            $this->commandBus->dispatch(new Index($index));
        }

        /** @var bool $wait */
        $wait = $input->getOption('wait');

        if ($wait) {
            /** @var mixed|null $waitTimeout */
            $waitTimeout = $input->getOption('wait-timeout');

            $this->wait((int) $waitTimeout, $output);
        }

        return 0;
    }

    private function wait(int $waitTimeout, OutputInterface $output): void
    {
        $start = time();

        $query = new TasksQuery();
        $query->setStatuses(['enqueued', 'processing']);

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
}
