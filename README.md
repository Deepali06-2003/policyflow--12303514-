# PolicyFlow — TripperShield Campus Engineering Challenge

A Laravel REST API for a fictional travel-insurance request, premium calculation, mock issuance, and reconciliation workflow. This is an assessment starter implementation, not a production insurance platform.

## Requirements

- PHP 8.2+
- Composer 2
- MySQL 8+ or PostgreSQL (SQLite in-memory is configured for automated tests)
- Git

## Local setup

1. Install dependencies (this repository includes a minimal Laravel 11 application skeleton):
   ```bash
   composer install
   ```
2. Create your local environment file and key:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
3. Create a local database named `policyflow` and set DB credentials in `.env`.
4. Run migrations:
   ```bash
   php artisan migrate
   ```
5. Start the server:
   ```bash
   php artisan serve
   ```
6. Run tests:
   ```bash
   php artisan test
   ```

## API endpoints

- `POST /api/policy-requests` — create a request, or return the existing request for the same `(agent_id, client_request_id)`.
- `GET /api/policy-requests` — paginated list; optional `?status=ISSUED`.
- `GET /api/policy-requests/{id}` — request, issuance attempts, and state history.
- `POST /api/policy-requests/{id}/submit` — submit to the mock insurer.
- `POST /api/mock-insurer/callback` — accept provider events.
- `GET /api/mock-insurer/policies/{providerReference}` — mock provider status lookup.

### Create request example

```bash
curl -X POST http://127.0.0.1:8000/api/policy-requests \
  -H 'Content-Type: application/json' \
  -d '{
    "client_request_id":"AGT-2026-0001",
    "agent_id":"AGENT-17",
    "customer_name":"Sample Customer",
    "date_of_birth":"1998-04-10",
    "destination":"INTERNATIONAL",
    "trip_start":"2026-11-10",
    "trip_end":"2026-11-14",
    "plan_code":"PLUS",
    "sum_insured":300000
  }'
```

### Submit example

Replace `1` with the ID returned by intake:
```bash
curl -X POST http://127.0.0.1:8000/api/policy-requests/1/submit \
  -H 'Content-Type: application/json' \
  -d '{"scenario":"SUCCESS"}'
```

Supported development scenarios: `SUCCESS`, `TEMPORARY_FAILURE`, `TIMEOUT_AFTER_ISSUE`, `DELAYED_CALLBACK`, `DUPLICATE_CALLBACK`.

## Pricing

`premium = daily_rate * inclusive_travel_days * age_multiplier * destination_multiplier * coverage_multiplier`

- Daily rates: BASIC ₹12, PLUS ₹20, PREMIUM ₹30.
- Age: 0–17 = 1.10; 18–60 = 1.00; 61–70 = 1.50; 71+ = 2.00.
- Destination: DOMESTIC = 1.00; INTERNATIONAL = 1.80.
- Coverage: ₹100,000 = 1.00; ₹300,000 = 1.25; ₹500,000 = 1.50.
- The stored premium is a snapshot and is not recalculated on retry.

## Reliability cases demonstrated

1. Duplicate intake: unique database constraint on `(agent_id, client_request_id)`, with duplicate response returning the original record.
2. Timeout after provider-side issuance: mark `NEEDS_RECONCILIATION`; next submission checks mock provider state before any new issuance attempt.
3. Duplicate callback: unique provider event ID and a state guard prevent repeated processing and regression from `ISSUED`.

## Architecture

- Controllers validate API input and shape responses.
- `PremiumCalculator` owns deterministic pricing.
- `PolicyWorkflow` owns status transitions, attempts, callbacks, and reconciliation.
- `MockInsurer` simulates insurer behavior locally.
- Migrations define database-level uniqueness and audit entities.

## Assumptions and known limitations

- This assessment uses fabricated customer data only.
- The mock provider is local and uses Laravel cache; use a persistent cache store if you want provider-side state to survive process restarts.
- For a production-grade integration, provider calls should not be performed while holding a database transaction; use an outbox/queue, provider idempotency keys, bounded retries, callback signature verification, and a robust reconciliation worker.
- The development `scenario` flag is intentionally exposed for testability and must be disabled or protected outside local development.
- Concurrent intake is protected by the database unique constraint. Run the tests and manually inspect behavior against your chosen DB engine.
- No real insurer, payment gateway, or personal data source is integrated.

## AI usage disclosure

See `AI_USAGE.md`. Review and verify all generated code before submission. You are responsible for understanding every implementation decision.
