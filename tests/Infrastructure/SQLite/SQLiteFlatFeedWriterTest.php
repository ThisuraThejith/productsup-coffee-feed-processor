<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\SQLite;

use App\Domain\Coffee\FlattenedCoffeeVariant;
use App\Infrastructure\SQLite\SQLiteFlatFeedWriter;
use PDO;
use PHPUnit\Framework\TestCase;

final class SQLiteFlatFeedWriterTest extends TestCase
{
    public function testItFailsWhenWritingBeforeReset(): void
    {
        $writer = new SQLiteFlatFeedWriter();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SQLite writer was not initialized.');

        $writer->write($this->createFlattenedRow());
    }

    public function testAbortIsSafeBeforeReset(): void
    {
        $writer = new SQLiteFlatFeedWriter();

        $writer->abort();

        self::assertTrue(true);
    }

    public function testAbortRollsBackUncommittedRows(): void
    {
        $path = sys_get_temp_dir() . '/coffee_feed_abort_test_' . uniqid('', true) . '.sqlite';

        $writer = new SQLiteFlatFeedWriter();

        $writer->reset($path);
        $writer->write($this->createFlattenedRow());
        $writer->abort();

        $pdo = new PDO('sqlite:' . $path);
        $count = (int) $pdo->query('SELECT COUNT(*) FROM coffee_variants')->fetchColumn();

        self::assertSame(0, $count);

        @unlink($path);
    }

    public function testItWritesFlattenedRowsToSQLite(): void
    {
        $path = sys_get_temp_dir() . '/coffee_feed_test_' . uniqid('', true) . '.sqlite';

        $writer = new SQLiteFlatFeedWriter();

        $writer->reset($path);
        $writer->write($this->createFlattenedRow());
        $writer->finish();

        $pdo = new PDO('sqlite:' . $path);
        $count = (int) $pdo->query('SELECT COUNT(*) FROM coffee_variants')->fetchColumn();

        self::assertSame(1, $count);

        $row = $pdo
            ->query('SELECT product_sku, variant_sku, variant_price_eur FROM coffee_variants LIMIT 1')
            ->fetch(PDO::FETCH_ASSOC);

        self::assertSame('BEAN-001', $row['product_sku']);
        self::assertSame('BEAN-001-250G-ESP', $row['variant_sku']);
        self::assertSame(12.50, (float) $row['variant_price_eur']);

        @unlink($path);
    }

    private function createFlattenedRow(): FlattenedCoffeeVariant
    {
        return new FlattenedCoffeeVariant(
            productSku: 'BEAN-001',
            productName: 'Test Coffee',
            originCountry: 'Ethiopia',
            originRegion: 'Sidamo',
            originFarm: 'Test Farm',
            originAltitudeM: 1600,
            originProcess: 'washed',
            originLat: null,
            originLng: null,
            roastLevel: 'medium',
            roastedOn: '2026-03-15',
            roaster: 'Test Roaster',
            flavorNotes: '["chocolate"]',
            tags: '["espresso"]',
            scoreAcidity: 7,
            scoreBody: 6,
            scoreSweetness: 8,
            scoreAroma: 7,
            scoreBitterness: 3,
            productInStock: true,
            description: null,
            variantSku: 'BEAN-001-250G-ESP',
            variantSize: '250g',
            variantGrind: 'espresso',
            variantPriceEur: 12.50,
            variantStock: 10,
        );
    }
}
