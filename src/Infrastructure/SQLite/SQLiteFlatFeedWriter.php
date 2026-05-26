<?php

declare(strict_types=1);

namespace App\Infrastructure\SQLite;

use App\Application\FlattenFeed\FlatFeedWriterInterface;
use App\Domain\Coffee\FlattenedCoffeeVariant;
use PDO;
use PDOStatement;
use RuntimeException;

class SQLiteFlatFeedWriter implements FlatFeedWriterInterface
{
    private ?PDO $pdo = null;

    private ?PDOStatement $insertStatement = null;

    public function reset(string $destination): void
    {
        $this->insertStatement = null;

        $directory = dirname($destination);

        // Defensive check for the directory before creating it to handle race conditions
        if(!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Could not create output directory "%s".', $directory));
        }

        if(is_file($destination) && !unlink($destination)) {
            throw new RuntimeException(sprintf('Could not remove existing SQLite database "%s".', $destination));
        }

        $this->pdo = new PDO('sqlite:' . $destination);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec($this->createTableSql());
        $this->pdo->beginTransaction();
    }

    public function write(FlattenedCoffeeVariant $row): void
    {
        if($this->pdo === null) {
            throw new RuntimeException('SQLite writer was not initialized.');
        }

        $data = $row->toArray();

        if($this->insertStatement === null) {
            $columns = array_keys($data);
            $placeholders = array_map(
                static fn(string $column): string => ':' . $column,
                $columns
            );

            $sql = sprintf(
                'INSERT INTO coffee_variants (%s) VALUES (%s)',
                implode(', ', $columns),
                implode(', ', $placeholders)
            );

            $this->insertStatement = $this->pdo->prepare($sql);
        }

        $this->insertStatement->execute($data);
    }

    public function finish(): void
    {
        if($this->pdo !== null && $this->pdo->inTransaction()) {
            $this->pdo->commit();
        }

        $this->insertStatement = null;
        $this->pdo = null;
    }

    public function abort(): void
    {
        if ($this->pdo !== null && $this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }

        $this->insertStatement = null;
        $this->pdo = null;
    }

    private function createTableSql(): string
    {
        return <<<SQL
CREATE TABLE coffee_variants (
    product_sku TEXT NOT NULL,
    product_name TEXT NOT NULL,
    origin_country TEXT NOT NULL,
    origin_region TEXT NOT NULL,
    origin_farm TEXT NOT NULL,
    origin_altitude_m INTEGER NULL,
    origin_process TEXT NOT NULL,
    origin_lat REAL NULL,
    origin_lng REAL NULL,
    roast_level TEXT NOT NULL,
    roasted_on TEXT NOT NULL,
    roaster TEXT NOT NULL,
    flavor_notes TEXT NOT NULL,
    tags TEXT NOT NULL,
    score_acidity INTEGER NOT NULL,
    score_body INTEGER NOT NULL,
    score_sweetness INTEGER NOT NULL,
    score_aroma INTEGER NOT NULL,
    score_bitterness INTEGER NOT NULL,
    product_in_stock INTEGER NOT NULL,
    description TEXT NULL,
    variant_sku TEXT NOT NULL,
    variant_size TEXT NOT NULL,
    variant_grind TEXT NOT NULL,
    variant_price_eur REAL NOT NULL,
    variant_stock INTEGER NOT NULL
)
SQL;
    }
}
