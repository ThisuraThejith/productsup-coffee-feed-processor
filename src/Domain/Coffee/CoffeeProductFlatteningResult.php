<?php

declare(strict_types=1);

namespace App\Domain\Coffee;

/**
 * Result object of flattening coffee products
 */
final readonly class CoffeeProductFlatteningResult
{
    /**
     * @param list<FlattenedCoffeeVariant> $rows
     * @param list<ValidationError> $errors
     */
    public function __construct(
        public array $rows,
        public array $errors = []
    ){}

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    public function errorMessages(): array
    {
        return array_map(
            static fn(ValidationError $error): string => $error->toMessage(),
            $this->errors
        );
    }
}
