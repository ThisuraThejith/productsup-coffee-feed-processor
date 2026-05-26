<?php

declare(strict_types=1);

namespace App\Infrastructure\Jsonl;

use JsonException;
use RuntimeException;

final class JsonlCoffeeFeedReader
{
    public function read(string $path): iterable
    {
        $this->assertReadable($path);

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException(sprintf('Could not open input file "%s".', $path));
        }

        $lineNumber = 0;

        try {
            while (($line = fgets($handle)) !== false) {
                $lineNumber++;

                $line = trim($line);

                if ($line === '') {
                    continue;
                }

                try {
                    $payload = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                } catch (JsonException $exception) {
                    yield [
                        'lineNumber' => $lineNumber,
                        'payload' => null,
                        'error' => 'Malformed JSON: ' . $exception->getMessage(),
                    ];

                    continue;
                }

                if (!is_array($payload)) {
                    yield [
                        'lineNumber' => $lineNumber,
                        'payload' => null,
                        'error' => 'JSON line must decode to an object.',
                    ];

                    continue;
                }

                yield [
                    'lineNumber' => $lineNumber,
                    'payload' => $payload,
                    'error' => null,
                ];
            }
        } finally {
            fclose($handle);
        }
    }

    public function assertReadable(string $path): void
    {
        if (!is_readable($path)) {
            throw new RuntimeException(sprintf('Input file "%s" is not readable.', $path));
        }
    }
}
