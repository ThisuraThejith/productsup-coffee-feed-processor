<?php

declare(strict_types=1);

namespace App\Domain\Coffee;

final readonly class ValidationError
{
    public function __construct(
        public string $path,
        public string $message,
        public string $code = 'invalid_value'
    ){}

    public function toMessage(): string
    {
        return sprintf('%s: %s', $this->path, $this->message);
    }
}
