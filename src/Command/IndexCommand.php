<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Command;

use Setono\SyliusMeilisearchPlugin\Config\IndexRegistryInterface;
use Setono\SyliusMeilisearchPlugin\Message\Command\Index;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
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
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('indexes', InputArgument::IS_ARRAY | InputArgument::OPTIONAL, 'Names of the index(es) to index', $this->indexRegistry->getNames());
    }

    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        /** @var list<string> $indexes */
        $indexes = $input->getArgument('indexes');

        foreach ($indexes as $index) {
            if (!$this->indexRegistry->has($index)) {
                throw new RuntimeException(sprintf('No index exists with the name "%s". Available indexes are: [%s]', $index, implode(', ', $this->indexRegistry->getNames())));
            }
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var list<string> $indexes */
        $indexes = $input->getArgument('indexes');

        foreach ($indexes as $index) {
            $this->commandBus->dispatch(new Index($index));
        }

        return 0;
    }
}
