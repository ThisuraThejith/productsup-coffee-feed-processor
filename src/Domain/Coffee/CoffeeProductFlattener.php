<?php

declare(strict_types=1);

namespace App\Domain\Coffee;

use JsonException;

final class CoffeeProductFlattener
{
    public function flatten(array $product): CoffeeProductFlatteningResult
    {
        $errors = new ValidationErrorBag();

        $productSku = $this->requiredString($product, 'sku', 'sku', $errors);
        $productName = $this->requiredString($product, 'name', 'name', $errors);

        $origin = $this->requiredObject($product, 'origin', 'origin', $errors);
        $roast = $this->requiredObject($product, 'roast', 'roast', $errors);
        $score = $this->requiredObject($product, 'tasting_score', 'tasting_score', $errors);

        $variants = $this->requiredNonEmptyList($product, 'variants', 'variants', $errors);
        $inStock = $this->requiredBool($product, 'in_stock', 'in_stock', $errors);

        if ($errors->hasErrors()) {
            return new CoffeeProductFlatteningResult([], $errors->all());
        }

        $sharedFields = $this->buildSharedProductFields(
            product: $product,
            productSku: $productSku,
            productName: $productName,
            origin: $origin,
            roast: $roast,
            score: $score,
            inStock: $inStock,
            errors: $errors
        );

        if ($errors->hasErrors()) {
            return new CoffeeProductFlatteningResult([], $errors->all());
        }

        $rows = [];

        foreach ($variants as $index => $variant) {
            if (!is_array($variant)) {
                $errors->add(
                    path: sprintf('variants[%d]', $index),
                    message: 'must be an object',
                    code: 'invalid_type',
                );
                continue;
            }

            $row = $this->flattenVariant(
                variant: $variant,
                index: $index,
                sharedFields: $sharedFields,
                errors: $errors
            );

            if ($row !== null) {
                $rows[] = $row;
            }
        }

        return new CoffeeProductFlatteningResult($rows, $errors->all());
    }

    private function buildSharedProductFields(
        array $product,
        string $productSku,
        string $productName,
        array $origin,
        array $roast,
        array $score,
        bool $inStock,
        ValidationErrorBag $errors
    ): array
    {
        $originCountry = $this->requiredString($origin, 'country', 'origin.country', $errors);
        $originRegion = $this->requiredString($origin, 'region', 'origin.region', $errors);
        $originFarm = $this->requiredString($origin, 'farm', 'origin.farm', $errors);
        $originProcess = $this->requiredString($origin, 'process', 'origin.process', $errors);

        $roastLevel = $this->requiredString($roast, 'level', 'roast.level', $errors);
        $roastedOn = $this->requiredString($roast, 'roasted_on', 'roast.roasted_on', $errors);
        $roaster = $this->requiredString($roast, 'roaster', 'roast.roaster', $errors);

        $scoreAcidity = $this->requiredInt($score, 'acidity', 'tasting_score.acidity', $errors);
        $scoreBody = $this->requiredInt($score, 'body', 'tasting_score.body', $errors);
        $scoreSweetness = $this->requiredInt($score, 'sweetness', 'tasting_score.sweetness', $errors);
        $scoreAroma = $this->requiredInt($score, 'aroma', 'tasting_score.aroma', $errors);
        $scoreBitterness = $this->requiredInt($score, 'bitterness', 'tasting_score.bitterness', $errors);

        return [
            'productSku' => $productSku,
            'productName' => $productName,
            'originCountry' => $originCountry,
            'originRegion' => $originRegion,
            'originFarm' => $originFarm,
            'originAltitudeM' => $this->optionalInt($origin, 'altitude_m'),
            'originProcess' => $originProcess,
            'originLat' => $this->optionalFloat($origin['coordinates'] ?? null, 'lat'),
            'originLng' => $this->optionalFloat($origin['coordinates'] ?? null, 'lng'),
            'roastLevel' => $roastLevel,
            'roastedOn' => $roastedOn,
            'roaster' => $roaster,
            'flavorNotes' => $this->optionalListToJson($product['flavor_notes'] ?? [], 'flavor_notes', $errors),
            'tags' => $this->optionalListToJson($product['tags'] ?? [], 'tags', $errors),
            'scoreAcidity' => $scoreAcidity,
            'scoreBody' => $scoreBody,
            'scoreSweetness' => $scoreSweetness,
            'scoreAroma' => $scoreAroma,
            'scoreBitterness' => $scoreBitterness,
            'productInStock' => $inStock,
            'description' => $this->optionalString($product, 'description')
        ];
    }

    private function flattenVariant(
        array $variant,
        int $index,
        array $sharedFields,
        ValidationErrorBag $errors
    ): ?FlattenedCoffeeVariant  {
        $variantErrors = new ValidationErrorBag();

        $variantSku = $this->requiredString(
            $variant,
            'sku_variant',
            sprintf('variants[%d].sku_variant', $index),
            $variantErrors
        );

        $variantSize = $this->requiredString(
            $variant,
            'size',
            sprintf('variants[%d].size', $index),
            $variantErrors
        );

        $variantGrind = $this->requiredString(
            $variant,
            'grind',
            sprintf('variants[%d].grind', $index),
            $variantErrors
        );

        $variantPrice = $this->requiredNonNegativeNumber(
            $variant,
            'price_eur',
            sprintf('variants[%d].price_eur', $index),
            $variantErrors
        );

        $variantStock = $this->requiredNonNegativeInteger(
            $variant,
            'stock',
            sprintf('variants[%d].stock', $index),
            $variantErrors
        );

        if($variantErrors->hasErrors()) {
            $errors->addMany($variantErrors->all());

            return null;
        }

        return new FlattenedCoffeeVariant(
            productSku: $sharedFields['productSku'],
            productName: $sharedFields['productName'],
            originCountry: $sharedFields['originCountry'],
            originRegion: $sharedFields['originRegion'],
            originFarm: $sharedFields['originFarm'],
            originAltitudeM: $sharedFields['originAltitudeM'],
            originProcess: $sharedFields['originProcess'],
            originLat: $sharedFields['originLat'],
            originLng: $sharedFields['originLng'],
            roastLevel: $sharedFields['roastLevel'],
            roastedOn: $sharedFields['roastedOn'],
            roaster: $sharedFields['roaster'],
            flavorNotes: $sharedFields['flavorNotes'],
            tags: $sharedFields['tags'],
            scoreAcidity: $sharedFields['scoreAcidity'],
            scoreBody: $sharedFields['scoreBody'],
            scoreSweetness: $sharedFields['scoreSweetness'],
            scoreAroma: $sharedFields['scoreAroma'],
            scoreBitterness: $sharedFields['scoreBitterness'],
            productInStock: $sharedFields['productInStock'],
            description: $sharedFields['description'],
            variantSku: $variantSku,
            variantSize: $variantSize,
            variantGrind: $variantGrind,
            variantPriceEur: $variantPrice,
            variantStock: $variantStock
        );
    }

    private function requiredString(
        array $data,
        string $key,
        string $path,
        ValidationErrorBag $errors
    ): ?string
    {
        $value = $data[$key] ?? null;

        if(!is_string($value) || trim($value) === '') {
            $errors->add(
                path: $path,
                message: 'must be a non-empty string',
                code: 'invalid_or_empty_string'
            );

            return null;
        }

        return trim($value);
    }

    private function requiredObject(
        array $data,
        string $key,
        string $path,
        ValidationErrorBag $errors
    ): array
    {
        $value = $data[$key] ?? null;

        if(!is_array($value)) {
            $errors->add(
                path: $path,
                message: 'must be an object',
                code: 'invalid_type'
            );

            return [];
        }

        return $value;
    }

    private function requiredNonEmptyList(
        array $data,
        string $key,
        string $path,
        ValidationErrorBag $errors
    ): array
    {
        $value = $data[$key] ?? null;

        if(!is_array($value) || $value === [] || !array_is_list($value)) {
            $errors->add(
                path: $path,
                message: 'must be a non-empty list',
                code: 'invalid_or_empty_list'
            );

            return [];
        }

        return $value;
    }

    private function requiredBool(
        array $data,
        string $key,
        string $path,
        ValidationErrorBag $errors
    ): ?bool
    {
        $value = $data[$key] ?? null;

        if(!is_bool($value)) {
            $errors->add(
                path: $path,
                message: 'must be a boolean',
                code: 'invalid_type'
            );

            return null;
        }

        return $value;
    }

    private function requiredInt(
        array $data,
        string $key,
        string $path,
        ValidationErrorBag $errors
    ): ?int
    {
        $value = $data[$key] ?? null;

        if (!is_int($value)) {
            $errors->add(
                path: $path,
                message: 'must be an integer',
                code: 'invalid_type'
            );

            return null;
        }

        return $value;
    }

    private function requiredNonNegativeInteger(
        array $data,
        string $key,
        string $path,
        ValidationErrorBag $errors
    ): ?int
    {
        $value = $this->requiredInt($data, $key, $path, $errors);

        if ($value === null) {
            return null;
        }

        if ($value < 0) {
            $errors->add(
                path: $path,
                message: 'must not be negative'
            );

            return null;
        }

        return $value;
    }

    private function requiredNonNegativeNumber(
        array $data,
        string $key,
        string $path,
        ValidationErrorBag $errors
    ): ?float
    {
        $value = $data[$key] ?? null;

        if (!is_int($value) && !is_float($value)) {
            $errors->add(
                path: $path,
                message: 'must be numeric',
                code: 'invalid_type'
            );

            return null;
        }

        $number = (float) $value;

        if ($number < 0) {
            $errors->add(
                path: $path,
                message: 'must not be negative'
            );

            return null;
        }

        return $number;
    }

    private function optionalString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_string($value) && trim($value) !== ''
            ? trim($value)
            : null;
    }

    private function optionalInt(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        return is_int($value) ? $value : null;
    }

    private function optionalFloat(mixed $container, string $key): ?float
    {
        if (!is_array($container)) {
            return null;
        }

        $value = $container[$key] ?? null;

        return is_int($value) || is_float($value)
            ? (float) $value
            : null;
    }

    private function optionalListToJson(
        mixed $value,
        string $path,
        ValidationErrorBag $errors
    ): string {
        if (!is_array($value) || !array_is_list($value)) {
            $errors->add(
                path: $path,
                message: 'must be a list; using empty list instead',
                code: 'invalid_type'
            );

            return '[]';
        }

        try {
            return json_encode($value, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $errors->add(
                path: $path,
                message: sprintf('could not be encoded as JSON: %s', $e->getMessage()),
                code: 'json_encode_failed'
            );

            return '[]';
        }
    }
}
