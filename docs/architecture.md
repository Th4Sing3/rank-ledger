# Architecture

## Intent

Rank Ledger is a modular Laravel application, not a generic leaderboard platform.
It accepts already-calculated score changes from trusted producers. It does not
decide how points are earned, manage users, or hold any financial state.

The write path needs one transaction across the score event, the current ranking
projection, the idempotency record, and a future webhook hand-off. Keeping that
workflow in one application makes the important consistency boundary obvious.

## Components

| Component | Responsibility | Does not decide |
| --- | --- | --- |
| Laravel HTTP layer | API-key authentication, input validation, HTTP responses | Game rules or score values |
| Action and services | Request fingerprinting, write orchestration, ranking snapshots, HMAC signing | Storage topology or user interface |
| MySQL | Event audit, projection, uniqueness constraints, transactions, outbox | Cache freshness or webhook retry policy |
| Redis | Read cache and Laravel queue transport | Event validity or uniqueness |
| Worker and scheduler | Dispatch pending outbox rows and deliver webhooks | Score mutation |
| Node receiver | A small local contract fixture for signature verification | Core application behaviour |

## Write path

```text
POST /api/v1/leaderboards/{id}/events
        │
        ├─ authenticate API client
        ├─ validate body and Idempotency-Key
        ├─ canonicalise request → SHA-256 fingerprint
        ▼
  one MySQL transaction
        ├─ reserve or replay Idempotency-Key
        ├─ lock the target leaderboard
        ├─ enforce event_id uniqueness
        ├─ append score_events
        ├─ update leaderboard_entries
        ├─ write outbox_messages
        └─ save the original HTTP response
        │
        ├─ advance the Redis cache version
        └─ queue the outbox dispatcher
```

The application locks the parent leaderboard row while changing its projection.
That is intentionally conservative: the MVP favours a clear consistency boundary
over an unproven high-throughput design. A larger system could partition, batch, or
asynchronously project leaderboards only when measured throughput calls for it.

## Idempotency and event deduplication

Two database constraints address two different failure modes:

- `idempotency_keys(api_client_id, idempotency_key)` protects a particular HTTP
  request. The canonical method, path, and JSON body are fingerprinted. A reused
  key with a different fingerprint is rejected.
- `score_events(leaderboard_id, external_event_id)` protects a business event.
  A producer can use a different HTTP idempotency key during a redelivery without
  increasing the score twice.

The projection, immutable event row, outbox message, and saved idempotency response
are created together. If the transaction does not commit, none of them becomes
visible.

## Read path

`GET /api/v1/leaderboards/{id}` reads the current projection in deterministic
order:

1. score descending;
2. oldest `last_event_at` among tied scores;
3. participant identifier ascending.

A short-lived cache stores a ranking snapshot under a per-leaderboard versioned
key. A committed write increments the version, so future reads use a new key
without treating Redis as a source of truth. A cache miss simply reads
`leaderboard_entries` from MySQL.

## Outbox and webhooks

The write transaction inserts an `outbox_messages` row. After commit,
`DispatchPendingOutboxMessages` queues a delivery job; the scheduler also scans
for still-pending messages every minute. Dispatch happens before the outbox row is
marked published. In the narrow failure window this can deliver a message twice,
which is preferable to silently dropping it.

`DeliverLeaderboardWebhook` signs the exact JSON body with
`HMAC-SHA256(timestamp + "." + body, secret)` and sends:

- `X-Rank-Ledger-Event`: stable business event ID;
- `X-Rank-Ledger-Delivery`: outbox delivery ID;
- `X-Rank-Ledger-Signature`: `t=<unix>,v1=<hex>`.

The receiver deduplicates business effects with the event ID. The worker retries
connection failures, `429`, and `5xx` responses with bounded backoff. Other
`4xx` responses are stored as permanent failures. Every attempt produces a
`webhook_deliveries` audit row without logging payload content.

## Data model

```text
api_clients
  ├─ leaderboards
  │    ├─ score_events          immutable audit, unique event ID per leaderboard
  │    ├─ leaderboard_entries   materialised ranking, unique participant per board
  │    └─ outbox_messages       durable notification hand-off
  ├─ idempotency_keys           one request key per client
  └─ webhook_deliveries         delivery-attempt audit
```

API keys are stored as digests. Webhook secrets use Laravel's encrypted model cast,
which makes a valid application key an explicit deployment requirement.

## Operational limits

- Redis is disposable for correctness; a Redis outage hurts latency or queue
  delivery, not the integrity of committed scores.
- The local Compose stack is a development environment, not a production deployment.
  TLS, managed secrets, destination allowlists, monitoring, backup policy, and
  operational ownership remain deployment concerns.
- Outbox delivery is **at least once**. A consumer must make its own side effect
  idempotent.
- The demo service handles a narrow workload. A production capacity plan should
  start with event rate, leaderboard cardinality, cache-hit rate, and observed
  database lock time rather than generic infrastructure.

