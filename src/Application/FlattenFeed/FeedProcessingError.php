<?php

declare(strict_types=1);

namespace App\Application\FlattenFeed;

final readonly class FeedProcessingError
{
    public function __construct(
        public int $lineNumber,
        public string $message,
    ) {}
}
