<?php

declare(strict_types=1);

namespace App\Application\FlattenFeed;

use App\Domain\Coffee\CoffeeProductFlattener;
use App\Infrastructure\Jsonl\JsonlCoffeeFeedReader;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class FlattenFeedHandler
{
    public function __construct(
        private JsonlCoffeeFeedReader $reader,
        private FlatFeedWriterInterface $writer,
        private CoffeeProductFlattener $flattener,
        private LoggerInterface $logger,
    ){}

    /**
     * @throws Throwable
     */
    public function handle(string $inputPath, string $destination): FlattenFeedResult
    {
        $runId = uniqid('feed_', true);

        $linesRead = 0;
        $productsProcessed = 0;
        $rowsWritten = 0;
        $errors = [];

        $this->logger->info('Feed flattening started.', [
            'run_id' => $runId,
            'input_path' => $inputPath,
            'destination' => $destination
        ]);

        try{
            $this->reader->assertReadable($inputPath);
            $this->writer->reset($destination);

            foreach($this->reader->read($inputPath) as $record) {
                $linesRead++;

                if($record['error'] !== null) {
                    $error = new FeedProcessingError(
                        lineNumber: $record['lineNumber'],
                        message: $record['error']
                    );

                    $errors[] = $error;

                    $this->logger->warning('Feed line skipped.', [
                        'run_id' => $runId,
                        'line_number' => $error->lineNumber,
                        'error_type' => 'invalid_jsonl_record',
                        'error' => $error->message,
                    ]);

                    continue;
                }

                $payload = $record['payload'];

                if($payload === null) {
                    continue;
                }

                $productsProcessed++;

                $productSku = $this->extractProductSku($payload);

                $flatteningResult = $this->flattener->flatten($payload);

                foreach($flatteningResult->errors as $validationError) {
                    $error = new FeedProcessingError(
                        lineNumber: $record['lineNumber'],
                        message: $validationError->toMessage()
                    );

                    $errors[] = $error;

                    $this->logger->warning('Product record issue.', [
                        'run_id' => $runId,
                        'line_number' => $error->lineNumber,
                        'product_sku' => $productSku,
                        'error_type' => 'product_flattening_error',
                        'field_path' => $validationError->path,
                        'error_code' => $validationError->code,
                        'error' => $validationError->message,
                    ]);
                }

                foreach($flatteningResult->rows as $row) {
                    $this->writer->write($row);
                    $rowsWritten++;
                }
            }

            $this->writer->finish();

            $this->logger->info('Feed flattening completed.', [
                'run_id' => $runId,
                'input_path' => $inputPath,
                'destination' => $destination,
                'lines_read' => $linesRead,
                'products_processed' => $productsProcessed,
                'rows_written' => $rowsWritten,
                'errors_logged' => count($errors),
            ]);
        } catch (Throwable $e) {
            $this->writer->abort();

            $this->logger->error('Feed flattening aborted.', [
                'run_id' => $runId,
                'input_path' => $inputPath,
                'destination' => $destination,
                'lines_read' => $linesRead,
                'products_processed' => $productsProcessed,
                'rows_written' => $rowsWritten,
                'errors_logged' => count($errors),
                'exception_class' => $e::class,
                'exception_message' => $e->getMessage()
            ]);

            throw $e;
        }

        return new FlattenFeedResult(
            inputPath: $inputPath,
            destination: $destination,
            linesRead: $linesRead,
            productsProcessed: $productsProcessed,
            rowsWritten: $rowsWritten,
            errors: $errors
        );
    }

    private function extractProductSku(array $payload): ?string
    {
        return isset($payload['sku']) && is_string($payload['sku'])
            ? $payload['sku']
            : null;
    }
}
