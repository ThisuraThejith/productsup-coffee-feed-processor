<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Jsonl;

use App\Infrastructure\Jsonl\JsonlCoffeeFeedReader;
use PHPUnit\Framework\TestCase;

final class JsonlCoffeeFeedReaderTest extends TestCase
{
    public function testItFailsWhenInputFileIsNotReadable(): void
    {
        $missingPath = sys_get_temp_dir() . '/missing_coffee_feed_' . uniqid('', true) . '.jsonl';

        $reader = new JsonlCoffeeFeedReader();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(sprintf(
            'Input file "%s" is not readable.',
            $missingPath
        ));

        iterator_to_array($reader->read($missingPath));
    }

    public function testItReadsValidLinesAndReportsMalformedLines(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'coffee_jsonl_');

        self::assertIsString($path);

        file_put_contents($path, <<<JSONL
{"sku":"BEAN-001"}
{broken-json}
{"sku":"BEAN-002"}
JSONL);

        $records = iterator_to_array((new JsonlCoffeeFeedReader())->read($path));

        self::assertCount(3, $records);

        self::assertSame(1, $records[0]['lineNumber']);
        self::assertNull($records[0]['error']);
        self::assertIsArray($records[0]['payload']);
        self::assertSame('BEAN-001', $records[0]['payload']['sku']);

        self::assertSame(2, $records[1]['lineNumber']);
        self::assertNull($records[1]['payload']);
        self::assertNotNull($records[1]['error']);
        self::assertIsString($records[1]['error']);
        self::assertStringStartsWith('Malformed JSON:', $records[1]['error']);
        self::assertStringContainsString('Syntax error', $records[1]['error']);

        self::assertSame(3, $records[2]['lineNumber']);
        self::assertNull($records[2]['error']);
        self::assertIsArray($records[2]['payload']);
        self::assertSame('BEAN-002', $records[2]['payload']['sku']);

        @unlink($path);
    }

    public function testItReportsJsonThatDoesNotDecodeToObject(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'coffee_jsonl_');

        self::assertIsString($path);

        file_put_contents($path, <<<JSONL
"not-an-object"
JSONL);

        $records = iterator_to_array((new JsonlCoffeeFeedReader())->read($path));

        self::assertCount(1, $records);

        self::assertSame(1, $records[0]['lineNumber']);
        self::assertNull($records[0]['payload']);
        self::assertNotNull($records[0]['error']);
        self::assertSame('JSON line must decode to an object.', $records[0]['error']);

        @unlink($path);
    }
}
