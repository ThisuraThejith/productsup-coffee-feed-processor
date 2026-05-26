<?php

declare(strict_types=1);

namespace App\Domain\Coffee;

/**
 * A simple error bag for collecting validation errors
 */
final class ValidationErrorBag
{
    private array $errors = [];

    public function add(string $path, string $message, string $code = 'invalid_value'): void
    {
        $this->errors[] = new ValidationError($path, $message, $code);
    }

    public function addMany(array $errors): void
    {
        foreach ($errors as $error) {
            $this->errors[] = $error;
        }
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }


    /**
     * @return list<ValidationError>
     */
    public function all(): array
    {
        return $this->errors;
    }
}
