<?php

require_once __DIR__ . '/vendor/autoload.php';
use MeiliSearch\Client;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\Table;

#[AsCommand(
    name: 'seed',
    description: 'Seed random data into meilisearch',
)]
class SeederCommand extends Command
{
    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $client = new Client('http://127.0.0.1:7700', 'thisisatest');
            $faker = Faker\Factory::create();
            $index = $client->index('test');
            $index->updateFilterableAttributes(['id', 'name', 'inc']);
            $totalEntries = 0;
            $progressBar = new \Symfony\Component\Console\Helper\ProgressBar($output, 1000);
            for ($x = 0; $x < 1000; $x++) {
                $data = [];
                for ($i = 0; $i < 100; $i++) {
                    $data[] = ['id' => $faker->uuid(), 'name' => $faker->name, 'url' => $faker->url(), 'misc' => $faker->realText(2056), 'inc' => $x];
                    $totalEntries++;
                }
                $index->addDocuments($data);
                $progressBar->advance();
                unset($data);
            }
            $progressBar->finish();
            $output->writeln('Total Entries: ' . number_format($totalEntries));
            return Command::SUCCESS;
        } catch (\Exception | \Error $e) {
            $output->writeln('ERR: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}

#[AsCommand(
    name: 'search',
    description: 'Search the meilisearch documents'
)]
class SearchCommand extends Command
{
    /**
     * @return void
     */
    protected function configure()
    {
        $this->addArgument('term', \Symfony\Component\Console\Input\InputArgument::REQUIRED, 'Term to search for');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $client = new Client('http://127.0.0.1:7700', 'thisisatest');
            $index = $client->index('test');
            $searchString = $input->getArgument('term');
            $output->writeln("Searching for.... { $searchString }");
            $hits = $index->search($searchString, ['limit' => 100]);
            $output->writeln($hits->toJSON());
            return Command::SUCCESS;
        } catch (\Exception | \Error $e) {
            $output->writeln('ERR: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}

#[AsCommand(
    name: 'clear',
    description: 'Removes all data from the index'
)]
class ClearCommand extends Command
{
    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $client = new Client('http://127.0.0.1:7700', 'thisisatest');
        $index = $client->index('test');
        $index->deleteAllDocuments();
        return Command::SUCCESS;
    }
}

#[AsCommand(
    name: 'stats'
)]
class MiscCommand extends Command
{
    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $client = new Client('http://127.0.0.1:7700', 'thisisatest');
        $stats = $client->stats();
        $table = new Table($output);
        $table->setHeaderTitle('Indexes');
        $table->setHeaders(['Index', 'Documents', 'Is Indexing']);
        foreach ($stats['indexes'] as $index => $data) {
            $table->addRow([
                $index,
                $data['numberOfDocuments'],
                $data['isIndexing'] ? 'Y' : 'N'
            ]);
        }

        $table->render();
        unset($table);
        $tasks = $client->getTasks((new \MeiliSearch\Contracts\TasksQuery())->setLimit(1000));
        $table = new Table($output);
        $table->setHeaderTitle('Tasks (' . $tasks->count() . ')');
        $table->setHeaders(['Index', 'Type', 'Status','Create At', 'Started At', 'Finished At']);
        foreach ($tasks as $task) {
            $table->addRow([
                $task['indexUid'],
                $task['type'],
                $task['status'],
                $task['enqueuedAt'],
                $task['startedAt'],
                $task['finishedAt']
            ]);
        }
        $table->render();
        return Command::SUCCESS;
    }
}

$app = new Application();
$app->add(new SeederCommand);
$app->add(new SearchCommand);
$app->add(new ClearCommand);
$app->add(new MiscCommand);
$app->run();

