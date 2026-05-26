# Productsup Coffee Feed Flattener

A small Symfony CLI application that reads a nested JSONL coffee product feed, flattens product variants into tabular rows, and writes the result into a SQLite database.

The application is designed as a small feed-processing pipeline:

```text
JSONL Reader
    ↓
Coffee Product Flattener
    ↓
SQLite Writer
    ↓
Processing Summary / Logs
```

## Requirements

- Docker
- Docker Compose

No host-level PHP, Composer, Symfony, or SQLite installation is required.

## How to run

From the project root:

```bash
docker compose up --build
```

This processes the bundled input file:

```text
input/coffee_feed.jsonl
```

and writes the SQLite output to:

```text
var/output/coffee_feed.sqlite
```

The command should print a summary similar to:

```text
Reading feed: input/coffee_feed.jsonl
Destination: var/output/coffee_feed.sqlite

Lines read: 500
Products processed: 500
Rows written: 1341
Errors logged: 0

Done.
```

Since this is a CLI application, the container runs the command and exits after processing is complete.

## Run manually

You can also run the command manually:

```bash
docker compose run --rm app php bin/console app:flatten-coffee-feed
```

With explicit paths:

```bash
docker compose run --rm app php bin/console app:flatten-coffee-feed input/coffee_feed.jsonl var/output/coffee_feed.sqlite
```

## Run tests

```bash
docker compose run --rm app php bin/phpunit
```

## Output

The application writes one flat SQLite table:

```text
coffee_variants
```

The output grain is:

```text
one row per product variant
```

This means that a product with three variants becomes three flat rows.

## Inspect the SQLite output

After running the processor, you can check the row count with:

```bash
docker compose run --rm app php -r '$pdo = new PDO("sqlite:var/output/coffee_feed.sqlite"); echo $pdo->query("SELECT COUNT(*) FROM coffee_variants")->fetchColumn() . PHP_EOL;'
```

Expected result:

```text
1341
```

You can also inspect a few rows with:

```bash
docker compose run --rm app php -r '$pdo = new PDO("sqlite:var/output/coffee_feed.sqlite"); foreach ($pdo->query("SELECT product_sku, product_name, variant_sku, variant_size, variant_price_eur, variant_stock FROM coffee_variants LIMIT 10") as $row) { print_r($row); }'
```

### Optional visual SQLite viewer

If you want to inspect the generated SQLite database visually, you can run a temporary SQLite web viewer container:

```bash
docker run --rm \
  -p 8081:8080 \
  -v "$PWD/var/output:/data" \
  coleifer/sqlite-web \
  sqlite_web --host 0.0.0.0 --port 8080 /data/coffee_feed.sqlite
```

Then open:

```text
http://localhost:8081
```

This step is optional and only meant for local inspection. The main application does not depend on this viewer.

## Design overview

The solution is split into small layers:

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
      ValidationError.php
      ValidationErrorBag.php

  Infrastructure/
    Jsonl/
      JsonlCoffeeFeedReader.php

    SQLite/
      SQLiteFlatFeedWriter.php
```

### Command layer

The Symfony command is only the CLI entry point.

It is responsible for:

- reading input/output paths
- calling the application handler
- printing the processing summary
- returning success/failure exit codes

It does not contain JSON parsing, flattening, or SQLite logic.

### Application layer

The application handler coordinates the use case:

```text
read JSONL record
→ flatten product
→ write valid rows
→ collect/log errors
→ return summary
```

It keeps the pipeline orchestration separate from the domain and infrastructure details.

### Domain layer

The domain layer contains the core flattening rule:

```text
one nested coffee product
→ one or more flat variant rows
```

The most important design decision is that the output is one row per variant, because `variants` is the repeating sellable unit in the input feed.

### Infrastructure layer

Infrastructure contains technical adapters:

- `JsonlCoffeeFeedReader` reads the JSONL file line by line.
- `SQLiteFlatFeedWriter` creates the SQLite table and writes flattened rows.

## Key design choices

### Symfony CLI

I used Symfony Console because this is a command-line feed-processing task, not a web/API task.

### SQLite output

I chose SQLite because it is simple, Docker-friendly, and still demonstrates a real tabular persistence target.

It avoids external database setup while still allowing the result to be queried and inspected.

### One row per variant

The input feed contains nested products with a repeating `variants` structure.

I chose one output row per variant because variants represent the sellable product options. Keeping one row per product would either lose variant data or keep nested data inside a supposedly flat output.

### Streaming JSONL reader

The reader processes the input file line by line instead of loading the whole file into memory.

The sample file is small, but JSONL naturally supports streaming, and this keeps the approach suitable for larger feeds.

### Writer interface

The output writer is behind `FlatFeedWriterInterface`.

The submitted implementation writes to SQLite, but another destination could be added later as another writer implementation without changing the reader, flattener, or handler.

### Structured validation errors

The flattener returns structured validation errors with:

- field path
- message
- error code

The application handler adds feed-level context such as the JSONL line number and logs the issue.

## Detailed design decisions

This README gives a short overview of the main decisions.

For more detailed reasoning about the architecture, trade-offs, validation approach, output shape, and possible extensions, see:

[DESIGN.md](DESIGN.md)

## Error handling

The application separates fatal infrastructure errors from row-level data errors.

Fatal errors include:

- input file not readable
- output directory cannot be created
- SQLite write failure
- unexpected runtime failures

These abort the process and trigger writer rollback.

Row-level errors include:

- malformed JSONL line
- invalid product structure
- invalid variant data

These are logged and skipped so one bad record does not stop the whole feed.

## Logging

The application uses structured PSR-3 logging.

It logs:

- feed processing start
- skipped JSONL lines
- product/variant validation issues
- feed processing completion
- aborted processing with exception details

Each run gets a generated `run_id` so logs from one processing run can be correlated.

## Assumptions

- Each JSONL line represents one product.
- Each product can contain multiple variants.
- The flat output should contain one row per variant.
- `description`, `origin.coordinates`, and `origin.altitude_m` are optional.
- `flavor_notes` and `tags` are stored as JSON strings to preserve list structure inside a flat table.
- Invalid variants can be skipped while valid variants from the same product can still be written.
- The output database is recreated on each run to keep local executions deterministic.

## What I would improve with more time

- Add a command option to select the output format.
- Add CSV/XML writers behind the existing writer interface.
- Add an automated profiling step to inspect field presence, null counts, type consistency, and array cardinality before processing.
- Add stricter schema validation and clearer validation categories.
- Add richer summary statistics, for example, rows per origin country or roast level.
- Add metrics for processing duration, rows processed, rows failed, and writer performance.
- Add idempotency using input file hash or processing job ID.
- Add checkpointing for very large files.
- Add a raw staging table for auditing and reprocessing.
- Add more integration tests around fatal writer failures and rollback behavior.

## AI usage

I used AI assistance to help reason about the input shape, architecture trade-offs, possible extension points, and README wording.

I reviewed and adapted the final implementation decisions myself.

For a real production ingestion pipeline, I would not rely on AI or manual inspection alone to determine feed assumptions. I would use automated profiling and validation to scan:

- malformed JSON lines
- field presence and missing fields
- null counts
- type consistency
- array cardinality
- schema drift between feed versions
