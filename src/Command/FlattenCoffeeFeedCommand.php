<?php

declare(strict_types=1);

namespace App\Command;

use App\Application\FlattenFeed\FlattenFeedHandler;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AsCommand(
    name: 'app:flatten-coffee-feed',
    description: 'Reads a JSONL coffee product feed, flattens variants into rows, and writes them to SQLite.'
)]
final class FlattenCoffeeFeedCommand extends Command
{
    private const DEFAULT_INPUT_PATH = 'input/coffee_feed.jsonl';
    private const DEFAULT_DESTINATION = 'var/output/coffee_feed.sqlite';

    public function __construct(
        private readonly FlattenFeedHandler $handler,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'inputPath',
                InputArgument::OPTIONAL,
                'Path to the input JSONL feed.',
                self::DEFAULT_INPUT_PATH
            )
            ->addArgument(
                'destination',
                InputArgument::OPTIONAL,
                'Path to the output SQLite database.',
                self::DEFAULT_DESTINATION
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $inputPath = (string) $input->getArgument('inputPath');
        $destination = (string) $input->getArgument('destination');

        $output->writeln(sprintf('Reading feed: %s', $inputPath));
        $output->writeln(sprintf('Destination: %s', $destination));
        $output->writeln('');

        try {
            $result = $this->handler->handle($inputPath, $destination);
        } catch (RuntimeException $exception) {
            $output->writeln(sprintf('<error>%s</error>', $exception->getMessage()));

            return Command::FAILURE;
        }
        catch (Throwable $exception) {
            $output->writeln(sprintf('<error>Unexpected error: %s</error>', $exception->getMessage()));

            return Command::FAILURE;
        }

        $output->writeln(sprintf('Lines read: %d', $result->linesRead));
        $output->writeln(sprintf('Products processed: %d', $result->productsProcessed));
        $output->writeln(sprintf('Rows written: %d', $result->rowsWritten));
        $output->writeln(sprintf('Errors logged: %d', $result->errorCount()));

        if ($result->errorCount() > 0) {
            $output->writeln('');
            $output->writeln('Errors:');

            foreach ($result->errors as $error) {
                $output->writeln(sprintf(
                    '- Line %d: %s',
                    $error->lineNumber,
                    $error->message
                ));
            }
        }

        $output->writeln('');
        $output->writeln('Done.');

        return Command::SUCCESS;
    }
}
