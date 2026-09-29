# StockTracker

Small inventory project with a Lumen REST API and a PHP CLI client.

It manages products, locations, stock movements, stock on hand, and a low-stock report.

## Project structure

```text
StockTracker/
├─ api/    # Lumen REST API
└─ cli/    # PHP command-line client
```

## Requirements

- PHP 8.1+
- Composer
- PDO SQLite
- cURL extension for the CLI

## Setup

### 1) API

From the project root:

```bash
cd api
composer install
```

If needed, copy the environment file:

```bash
cp .env.example .env
```

The app uses SQLite files in `api/database/`.

If the database is not ready yet, run migrations:

```bash
php artisan migrate
```

Optional seed data:

```bash
php artisan db:seed
```

Start the API server:

```bash
php -S localhost:8008 -t public
```

### 2) CLI

In another terminal:

```bash
cd cli
composer install
```

The CLI talks to the API at `http://localhost:8008` by default.

To use a different API URL:

```bash
set API_BASE_URL=http://localhost:8000
```

## API behavior

### Products

- `GET /products`
- `POST /products`
- `GET /products/{id}`
- `PATCH /products/{id}`
- `DELETE /products/{id}`

### Locations

- `GET /locations`
- `POST /locations`
- `GET /locations/{id}`
- `PATCH /locations/{id}`
- `DELETE /locations/{id}`

### Movements

- `GET /movements`
- `POST /movements`

Movement types:

- `IN`
- `OUT`
- `ADJUST`

Rules:

- `qty` must be non-zero
- `IN` is stored positive
- `OUT` is stored negative
- `ADJUST` keeps its sign
- `OUT` is rejected if it would make stock negative

### Stock

- `GET /stocks`
- `GET /products/{id}/stocks`
- `GET /locations/{id}/stocks`

### Reports

- `GET /reports/low-stock`

## CLI commands

### Products

```bash
php bin/stock products:list
php bin/stock products:show 1
php bin/stock products:create --sku=... --name=... --unit-cost-cents=... --min-stock=...
php bin/stock products:update 1 --name=...
php bin/stock products:delete 1
```

### Locations

```bash
php bin/stock locations:list
php bin/stock locations:show 1
php bin/stock locations:create --code=... --address=...
php bin/stock locations:update 1 --address=...
php bin/stock locations:delete 1
```

### Movements

```bash
php bin/stock movements:list
php bin/stock movements:create --product-id=1 --location-id=1 --type=IN --qty=20
```

### Stock

```bash
php bin/stock stocks:list
php bin/stock stocks:product 1
php bin/stock stocks:location 1
php bin/stock reports:low-stock
```

## Tests

API tests live in:

```text
api/tests/Feature/
```

Run them from `api/`:

```bash
vendor/bin/pest
```

## Notes

- The API is the source of truth
- The CLI is a separate client for the same backend
- The project uses SQLite for local development
