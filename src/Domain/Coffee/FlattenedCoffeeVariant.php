<?php

declare(strict_types=1);

namespace App\Domain\Coffee;

/**
 * Domain model object for a flattened coffee variant. Represents one flat output row.
 */
final readonly class FlattenedCoffeeVariant
{
    public function __construct(
      public string $productSku,
      public string $productName,
      public string $originCountry,
      public string $originRegion,
      public string $originFarm,
      public ?int $originAltitudeM,
      public string $originProcess,
      public ?float $originLat,
      public ?float $originLng,
      public string $roastLevel,
      public string $roastedOn,
      public string $roaster,
      public string $flavorNotes,
      public string $tags,
      public int $scoreAcidity,
      public int $scoreBody,
      public int $scoreSweetness,
      public int $scoreAroma,
      public int $scoreBitterness,
      public bool $productInStock,
      public ?string $description,
      public string $variantSku,
      public string $variantSize,
      public string $variantGrind,
      public float $variantPriceEur,
      public int $variantStock
    ){}

    public function toArray(): array
    {
        return [
          'product_sku' => $this->productSku,
          'product_name' => $this->productName,
          'origin_country' => $this->originCountry,
          'origin_region' => $this->originRegion,
          'origin_farm' => $this->originFarm,
          'origin_altitude_m' => $this->originAltitudeM,
          'origin_process' => $this->originProcess,
          'origin_lat' => $this->originLat,
          'origin_lng' => $this->originLng,
          'roast_level' => $this->roastLevel,
          'roasted_on' => $this->roastedOn,
          'roaster' => $this->roaster,
          'flavor_notes' => $this->flavorNotes,
          'tags' => $this->tags,
          'score_acidity' => $this->scoreAcidity,
          'score_body' => $this->scoreBody,
          'score_sweetness' => $this->scoreSweetness,
          'score_aroma' => $this->scoreAroma,
          'score_bitterness' => $this->scoreBitterness,
          'product_in_stock' => $this->productInStock ? 1 : 0,
          'description' => $this->description,
          'variant_sku' => $this->variantSku,
          'variant_size' => $this->variantSize,
          'variant_grind' => $this->variantGrind,
          'variant_price_eur' => $this->variantPriceEur,
          'variant_stock' => $this->variantStock
        ];
    }
}
