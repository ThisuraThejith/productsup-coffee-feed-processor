# Design Notes — Productsup Coffee Feed Flattener

## 1. Goal

Build a Docker-runnable Symfony CLI application that reads a nested JSONL product feed, flattens it into tabular rows, writes the result to a destination, and logs processing errors.

The case study asks for a command-line program that:

- reads the input file from an argument or environment variable
- flattens nested product data into row-shaped records
- writes the result to a destination
- logs errors
- runs via Docker
- includes meaningful tests
- includes a short README explaining design choices (which is done here), next steps, and AI usage

---

## 2. Chosen Stack

- PHP 8.3
- Symfony 7.x
- Symfony Console
- SQLite via PDO
- Docker + Docker Compose
- PHPUnit

This stack keeps the solution close to the preferred stack while avoiding unnecessary infrastructure such as a web server, external database service, queue, or Kubernetes setup.

---

## 3. Output Destination

I chose **SQLite** as the output destination.

Reasons:

- It is explicitly allowed by the assignment.
- It is Docker-friendly.
- It requires no external service or authentication.
- It gives a real tabular persistence target.
- It is more backend-oriented than a plain CSV file.
- It remains simple enough for the given time-box.

The output database is written to:

```text
var/output/coffee_feed.sqlite
```

The main table is:

```text
coffee_variants
```

---

## 4. Output Grain

The output uses **one row per product variant**.

The input feed contains products, and each product can contain multiple variants.

Example:

```text
1 product
  ├── variant A
  ├── variant B
  └── variant C

= 3 flat output rows
```

This is the core flattening decision that has been made.

A product-level row would either lose variant data or keep nested variant data inside a supposedly flat output. Since variants represent the repeating sellable unit, they seem to be the natural row grain.

---

## 5. High-Level Flow

```text
JSONL input file
      |
      v
JsonlCoffeeFeedReader
      |
      v
FlattenFeedHandler
      |
      v
CoffeeProductFlattener
      |
      v
SQLiteFlatFeedWriter
      |
      v
coffee_variants table
```

Simplified pipeline:

```text
Read line
  -> Decode JSON
  -> Flatten product into variant rows
  -> Write rows to SQLite
  -> Collect/log errors
  -> Print summary
```

---

## 6. Planned Project Structure

```text
src/
  Command/
    FlattenCoffeeFeedCommand.php

  Application/
    FlattenFeed/
      FlatFeedWriterInterface.php
      FeedProcessingError.php
      FlattenFeedHandler.php
      FlattenFeedResult.php

  Domain/
    Coffee/
      CoffeeProductFlattener.php
      CoffeeProductFlatteningResult.php
      FlattenedCoffeeVariant.php
      ValidationErrorBag.php

  Infrastructure/
    Jsonl/
      JsonlCoffeeFeedReader.php

    SQLite/
      SQLiteFlatFeedWriter.php

tests/
  Domain/
    CoffeeProductFlattenerTest.php

  Infrastructure/
    Jsonl/
        JsonlCoffeeFeedReaderTest.php
    SQLite/
        SQLiteFlatFeedWriterTest.php
```

---

## 7. Layer Responsibilities

### Command Layer

File:

```text
src/Command/FlattenCoffeeFeedCommand.php
```

Responsibilities:

- expose the Symfony CLI command
- read command arguments
- call the application handler
- print processing summary
- return success or failure status

The command should not contain JSONL parsing, flattening rules, or SQLite details.

---

### Application Layer

Files:

```text
src/Application/FlattenFeed/FlattenFeedHandler.php
src/Application/FlattenFeed/FlattenFeedResult.php
src/Application/FlattenFeed/FeedProcessingError.php
src/Application/FlattenFeed/FlatFeedWriterInterface.php
```

Responsibilities:

- coordinate the use case
- read feed records
- call the flattener
- send flat rows to the writer
- collect processing counts
- collect and log errors

The application layer acts as the orchestration layer.

It should know the flow:

```text
reader -> flattener -> writer
```

but it should not know the low-level details of JSONL decoding or SQLite inserts.

---

### Domain Layer

Files:

```text
src/Domain/Coffee/CoffeeProductFlattener.php
src/Domain/Coffee/CoffeeProductFlatteningResult.php
src/Domain/Coffee/FlattenedCoffeeVariant.php
src/Domain/Coffee/ValidationError.php
src/Domain/Coffee/ValidationErrorBag.php
```

Responsibilities:

- define how a nested coffee product becomes flat rows
- validate required product-level fields
- validate required variant-level fields
- treat optional fields as nullable
- convert arrays like `flavor_notes` and `tags` into JSON strings
- create one `FlattenedCoffeeVariant` per valid variant

The domain layer contains the core business decision:

```text
one product with N variants = N flat rows
```

---

### Infrastructure Layer

Files:

```text
src/Infrastructure/Jsonl/JsonlCoffeeFeedReader.php
src/Infrastructure/SQLite/SQLiteFlatFeedWriter.php
```

Responsibilities:

- read JSONL line by line
- decode JSON
- report malformed JSON lines
- create output directory if needed
- create SQLite database/table
- insert flattened rows into SQLite

Infrastructure classes handle technical details and should be replaceable.

---

## 8. SQLite Table Shape

The flat table is planned as:

```sql
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
);
```

---

## 9. Field Handling

### Required Product Fields

These are required because they are needed to produce a meaningful flat row:

```text
sku
name
origin
roast
tasting_score
in_stock
variants
```

Important nested fields:

```text
origin.country
origin.region
origin.farm
origin.process

roast.level
roast.roasted_on
roast.roaster

tasting_score.acidity
tasting_score.body
tasting_score.sweetness
tasting_score.aroma
tasting_score.bitterness
```

### Required Variant Fields

These are required because each output row is based on a variant:

```text
variant.sku_variant
variant.size
variant.grind
variant.price_eur
variant.stock
```

### Optional Fields

These fields can be missing or null:

```text
description
origin.altitude_m
origin.coordinates.lat
origin.coordinates.lng
```

If missing, they become `NULL` in SQLite.

### Array Fields

These fields are stored as JSON strings:

```text
flavor_notes
tags
```

Reason:

A single flat table cannot naturally store arrays without creating additional tables. Encoding these arrays as JSON strings keeps the table flat while preserving the original list structure better than comma-separated text.

---

## 10. Error Handling Strategy

### Fatal Errors

These stop the command:

```text
input file does not exist
input file is not readable
output directory cannot be created
SQLite database cannot be created
SQLite write failure
```

These are infrastructure-level failures.

### Row-Level Errors

These are logged and skipped without stopping the whole feed:

```text
malformed JSON line
missing required product field
invalid product structure
missing required variant field
invalid variant price
invalid variant stock
```

This allows the feed processor to keep useful data even if some records are bad.

### Optional Missing Fields

These are not treated as errors:

```text
missing description
missing coordinates
null altitude
empty flavor_notes
empty tags
```

These are enrichment/descriptive fields, not required to produce a valid output row.

---

## 11. Large File Considerations

The input is JSONL, which is suitable for streaming.

The reader should process the file line by line:

```text
read one line
decode one product
flatten it
write resulting rows
discard the product from memory
continue
```

The application should avoid collecting all flattened rows in memory.

The SQLite writer should insert rows incrementally and use a transaction for better write performance.

---

## 12. Testing Strategy

The tests should demonstrate meaningful behavior rather than chase high coverage.

Planned tests:

### CoffeeProductFlattenerTest

Covers:

```text
one product with two variants creates two flat rows
missing optional fields are allowed
stock = 0 is valid
missing required fields produce errors
```

### JsonlCoffeeFeedReaderTest

Covers:

```text
valid JSONL lines are decoded
malformed JSON lines are reported with line numbers
reader continues after malformed lines
```

### SQLiteFlatFeedWriterTest

Covers:

```text
SQLite table is created
a flattened row is inserted
row count can be queried
```

---

## 13. Summary of Main Decisions

```text
Input format:
JSONL, read line by line.

Output grain:
one row per product variant.

Output destination:
SQLite.

Architecture:
small clean pipeline with command, application, domain, and infrastructure layers.

Error policy:
fatal infrastructure errors stop the command;
row-level data errors are logged and skipped.

Time-box strategy:
build the core cleanly, stop, and document what would be improved next.
```
