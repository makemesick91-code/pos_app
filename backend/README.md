# Aish POS Lite — Backend API

Laravel API backend for the **Aish POS Lite** Android POS SaaS.

> Source of truth: [`../docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md`](../docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md)

Sprint 0 provides only the API skeleton and an infrastructure **health endpoint**.
No business POS features (tenant, product, sales, QRIS, subscription) exist yet —
those are introduced in later sprints per the foundation roadmap.

## Requirements

- PHP 8.2+
- Composer 2
- PostgreSQL 14+ (for real runs; automated tests use in-memory SQLite)

## Local Setup

```bash
# 1. Install dependencies
composer install

# 2. Copy environment file
cp .env.example .env

# 3. Generate the application key
php artisan key:generate

# 4. Run migrations (requires PostgreSQL configured in .env)
php artisan migrate

# 5. Run the development server
php artisan serve --host=127.0.0.1 --port=8000
```

## Health Endpoint

```bash
curl -s http://127.0.0.1:8000/api/health
```

Expected response:

```json
{
  "status": "ok",
  "app": "Aish POS Lite API",
  "foundation": "POS_ANDROID_SAAS_FOUNDATION",
  "sprint": "Sprint 0"
}
```

## Testing

Tests run against an in-memory SQLite database and do **not** require PostgreSQL:

```bash
php artisan test
```

The health endpoint has no database dependency, so `tests/Feature/HealthTest.php`
validates it in any environment.
