<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Domain\Coffee\CoffeeProductFlattener;
use PHPUnit\Framework\TestCase;

final class CoffeeProductFlattenerTest extends TestCase
{
    public function testItCreatesOneFlatRowPerVariant(): void
    {
        $product = $this->validProduct();

        $result = (new CoffeeProductFlattener())->flatten($product);

        self::assertFalse($result->hasErrors());
        self::assertSame([], $result->errors);
        self::assertCount(2, $result->rows);

        self::assertSame('BEAN-001', $result->rows[0]->productSku);
        self::assertSame('BEAN-001', $result->rows[1]->productSku);
        self::assertSame('BEAN-001-250G-ESP', $result->rows[0]->variantSku);
        self::assertSame('BEAN-001-1KG-WHOLE', $result->rows[1]->variantSku);

        self::assertSame('["chocolate","citrus"]', $result->rows[0]->flavorNotes);
        self::assertSame('["espresso","organic"]', $result->rows[0]->tags);
    }

    public function testItAllowsMissingOptionalFields(): void
    {
        $product = $this->validProduct();

        unset($product['description']);
        unset($product['origin']['coordinates']);

        $product['origin']['altitude_m'] = null;
        $product['variants'] = [
            [
                'size' => '250g',
                'grind' => 'espresso',
                'price_eur' => 12.50,
                'stock' => 0,
                'sku_variant' => 'BEAN-001-250G-ESP',
            ],
        ];

        $result = (new CoffeeProductFlattener())->flatten($product);

        self::assertFalse($result->hasErrors());
        self::assertCount(1, $result->rows);

        $row = $result->rows[0];

        self::assertNull($row->description);
        self::assertNull($row->originLat);
        self::assertNull($row->originLng);
        self::assertNull($row->originAltitudeM);
        self::assertSame(0, $row->variantStock);
    }

    public function testItReportsMissingRequiredFields(): void
    {
        $result = (new CoffeeProductFlattener())->flatten([
            'name' => 'Broken Coffee',
            'variants' => [],
        ]);

        self::assertTrue($result->hasErrors());
        self::assertSame([], $result->rows);

        self::assertContains('sku: must be a non-empty string', $result->errorMessages());
        self::assertContains('origin: must be an object', $result->errorMessages());
        self::assertContains('roast: must be an object', $result->errorMessages());
        self::assertContains('tasting_score: must be an object', $result->errorMessages());
        self::assertContains('variants: must be a non-empty list', $result->errorMessages());
    }

    public function testItSkipsInvalidVariantButKeepsValidVariant(): void
    {
        $product = $this->validProduct();

        $product['variants'][1]['price_eur'] = -10.00;

        $result = (new CoffeeProductFlattener())->flatten($product);

        self::assertTrue($result->hasErrors());
        self::assertCount(1, $result->rows);
        self::assertSame('BEAN-001-250G-ESP', $result->rows[0]->variantSku);

        self::assertContains(
            'variants[1].price_eur: must not be negative',
            $result->errorMessages()
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validProduct(): array
    {
        return [
            'sku' => 'BEAN-001',
            'name' => 'Test Coffee',
            'origin' => [
                'country' => 'Ethiopia',
                'region' => 'Sidamo',
                'farm' => 'Test Farm',
                'altitude_m' => 1600,
                'process' => 'washed',
                'coordinates' => [
                    'lat' => 6.74,
                    'lng' => 38.41,
                ],
            ],
            'roast' => [
                'level' => 'medium',
                'roasted_on' => '2026-03-15',
                'roaster' => 'Test Roaster',
            ],
            'flavor_notes' => ['chocolate', 'citrus'],
            'tags' => ['espresso', 'organic'],
            'tasting_score' => [
                'acidity' => 7,
                'body' => 6,
                'sweetness' => 8,
                'aroma' => 7,
                'bitterness' => 3,
            ],
            'in_stock' => true,
            'description' => 'Test description',
            'variants' => [
                [
                    'size' => '250g',
                    'grind' => 'espresso',
                    'price_eur' => 12.50,
                    'stock' => 10,
                    'sku_variant' => 'BEAN-001-250G-ESP',
                ],
                [
                    'size' => '1kg',
                    'grind' => 'whole-bean',
                    'price_eur' => 40.00,
                    'stock' => 5,
                    'sku_variant' => 'BEAN-001-1KG-WHOLE',
                ],
            ],
        ];
    }
}
