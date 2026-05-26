<?php

declare(strict_types=1);

namespace App\Application\FlattenFeed;

use App\Domain\Coffee\FlattenedCoffeeVariant;

interface FlatFeedWriterInterface
{
    public function reset(string $destination): void;

    public function write(FlattenedCoffeeVariant $row): void;

    public function finish(): void;

    public function abort(): void;
}
