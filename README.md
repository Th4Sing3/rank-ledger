# Rank Ledger

[![CI](../../actions/workflows/ci.yml/badge.svg)](../../actions/workflows/ci.yml)

Rank Ledger is a compact Laravel service for accepting idempotent leaderboard
events and serving a reliable ranking. It focuses on the behaviour that tends to
get lost behind a seemingly simple score endpoint: duplicate delivery, concurrent
writes, audit history, cache invalidation, and post-commit notifications.

Scores are signed integers only. This is not a game, payment, wallet, cryptocurrency,
or betting system.

> This is an independent reference implementation. It is not affiliated with,
> endorsed by, or derived from another company or codebase.

## What it demonstrates

- **Laravel / PHP 8.3** for a focused modular monolith;
- **MySQL 8** as the transactional source of truth;
- **Redis 7** for short-lived cached reads and Laravel queues;
- **Node.js 22** for a dependency-free webhook contract receiver;
- **Docker Compose** for a runnable local stack;
- idempotency, business-event deduplication, a transactional outbox, bounded
  webhook retries, tests, CI, and recorded decisions.

## The problem

A trusted producer reports a score event. HTTP clients retry; producers can resend
the same business event; and unrelated events may arrive at the same time. Rank
Ledger stores a score event once, updates the materialised entry in the same MySQL
transaction, and writes an outbox message before any webhook can be delivered.

```text
producer
   │  POST score event + Idempotency-Key
   ▼
Laravel API ─────────────► MySQL
   │                         ├─ score_events          immutable audit trail
   │                         ├─ leaderboard_entries   current projection
   │                         ├─ idempotency_keys      HTTP retry protection
   │                         └─ outbox_messages       durable hand-off
   │
   ├─ versioned cache ────► Redis
   │
   └─ worker ────────────► signed webhook ───────────► Node demo receiver
```

Two separate safeguards matter:

| Safeguard | Protects | Behaviour |
| --- | --- | --- |
| `Idempotency-Key` | an HTTP request retry | The same key and canonical request replay the saved response. Reusing the key for other input returns `409 Conflict`. |
| `event_id` | a business-event redelivery | A matching event does not increment the score twice; changed data for the same event ID returns `409 Conflict`. |

## Quick start

### Prerequisites

- Docker Desktop with Docker Compose v2
- `curl` or PowerShell for the example calls

### Start the local stack

```bash
cp .env.example .env
# Set a unique APP_KEY before starting the application.
docker compose up --build
```

On Windows PowerShell, the first command is `Copy-Item .env.example .env`. If PHP
is available, run `php artisan key:generate`. Otherwise generate 32 random bytes
with a local secret manager and set `APP_KEY=base64:<value>` in `.env`.

Compose starts MySQL and Redis, runs the one-shot `migrate` service, then starts the
API, worker, scheduler, and Node receiver. The API is available at
`http://localhost:8080`; the receiver is available at `http://localhost:8081`.

The values in `.env.example` are local-demo credentials only. Replace them outside
local development, and never commit `.env`.

### Submit an event

The seeder creates the `daily-demo` leaderboard for the local demo API key:

```bash
export RANK_LEDGER_API_KEY='replace-with-your-local-demo-key'

curl --request POST 'http://localhost:8080/api/v1/leaderboards/daily-demo/events' \
  --header "X-API-Key: ${RANK_LEDGER_API_KEY}" \
  --header 'Idempotency-Key: 8705c278-3a40-4ff0-8ca2-b24f3b92a142' \
  --header 'Content-Type: application/json' \
  --data '{
    "event_id": "round_01JMY9P5QKAZMHZJ8HBP3Z2TZG",
    "participant_id": "player_ada",
    "points": 125,
    "occurred_at": "2026-11-11T18:00:00Z",
    "metadata": { "source": "demo", "round_reference": "round-42" }
  }'
```

Fetch the current ranking:

```bash
curl --request GET 'http://localhost:8080/api/v1/leaderboards/daily-demo?limit=10' \
  --header "X-API-Key: ${RANK_LEDGER_API_KEY}"
```

See [the API contract](docs/api.md) for the response shapes, duplicate semantics,
and webhook contract.

## Scope and deliberate omissions

The repository is intentionally a small product slice. It includes score ingestion,
ranking reads, API-key authentication, MySQL constraints, Redis, a transactional
outbox, and signed webhooks. It deliberately excludes a dashboard, user accounts,
payments, game rules, real-time sockets, generic rules engines, and speculative
microservices. The reasoning is recorded in [the delivery analysis](docs/analysis.md)
and [the ADRs](docs/adr/).

## Run checks

With PHP and Composer available locally:

```bash
composer validate --strict
composer install
php artisan test
vendor/bin/pint --test
npm --prefix examples/webhook-receiver test
```

The Docker development image deliberately includes development dependencies, so the
same checks can also run without a host PHP installation:

```bash
docker compose run --rm app php artisan test
docker compose run --rm app vendor/bin/pint --test
docker compose run --rm webhook-receiver npm test
```

The GitHub Actions workflow validates Composer metadata, runs the Laravel suite,
checks formatting, runs the Node contract tests, and renders the Compose file.

## Repository layout

```text
app/
  Actions/       # application orchestration
  Data/          # immutable input and result objects
  Exceptions/    # explicit conflict cases
  Http/          # middleware, validation, and controllers
  Jobs/          # outbox dispatch and webhook delivery
  Models/        # persistence mapping
  Services/      # fingerprinting, snapshots, and signing
database/
  migrations/    # database-level invariants
docs/            # API, architecture, security, ADRs, analysis, work log
examples/
  webhook-receiver/  # Node 22 webhook contract receiver
docker/          # Laravel image and entrypoint
tests/           # feature and unit coverage
```

## Further reading

- [Architecture](docs/architecture.md)
- [Security notes](docs/security.md)
- [Applied design principles](docs/principles.md)
- [Contribution guide](CONTRIBUTING.md)
- [Changelog](CHANGELOG.md)
- [Work log](docs/worklog.md)

## License

Rank Ledger is available under the [MIT License](LICENSE).

