<?php

declare(strict_types=1);

namespace App\Application\FlattenFeed;

final readonly class FlattenFeedResult
{
    public function __construct(
        public string $inputPath,
        public string $destination,
        public int $linesRead,
        public int $productsProcessed,
        public int $rowsWritten,
        public array $errors = []
    ){}

    public function errorCount(): int
    {
        return count($this->errors);
    }
}
