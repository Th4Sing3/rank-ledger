# Delivery analysis

## Objective

Create a compact, readable backend reference implementation that demonstrates the
engineering concerns behind a live leaderboard: correct score ingestion under
retries, a queryable ranking, auditability, cache use, and reliable integration
notifications.

The implementation is intentionally designed as a credible small product slice,
not a generic framework demonstration or a full game platform.

## Context

The target role asks for senior backend judgement with PHP/Laravel, Node.js, MySQL,
Redis, Docker, independent problem solving, and practical use of DRY, KISS, YAGNI,
and SOLID. The public product context makes a leaderboard-oriented backend problem
plausible without recreating a third party product or handling financial activity.

The starting workspace was empty. The available local tooling included Git and
Node.js; PHP, Composer, and Docker were not installed on the workstation. The
repository is therefore structured for Docker and CI execution while local static
and Node-level validation remain possible.

## Scope

### Included

- Laravel API for submitting and reading leaderboard score events;
- API-key authentication, request validation, and clear error responses;
- HTTP idempotency plus business-event deduplication;
- MySQL migrations and constraints for the source of truth;
- immutable score-event audit trail and materialised ranking projection;
- Redis-backed cache and queue configuration;
- transactional outbox and signed, retried webhook delivery;
- a small Node.js webhook receiver used to verify the integration contract;
- Docker Compose environment, tests, CI, and operational documentation.

### Explicitly excluded

- a dashboard or Vue client;
- user registration, session management, and role administration;
- payments, wallets, currencies, betting, rewards, or commission calculation;
- game rules, tournament management, real-time socket broadcasting, and chat;
- speculative microservices, event brokers, CQRS infrastructure, or multi-region
  replication;
- production infrastructure such as TLS termination, secret rotation, monitoring,
  backups, and incident response tooling.

These exclusions are intentional. They preserve the focus on backend correctness
and leave later additions driven by a confirmed product need.

## Deliverables

| Deliverable | Evidence |
| --- | --- |
| Laravel application slice | `app/`, `routes/`, `database/migrations/`, and `tests/` |
| Local runtime | `compose.yaml`, `docker/`, `.env.example` |
| Node integration receiver | `examples/webhook-receiver/` |
| API contract | `docs/api.md` |
| Architecture and decisions | `docs/architecture.md`, `docs/adr/` |
| Security posture | `docs/security.md` |
| Design-principle mapping | `docs/principles.md` |
| Contributor and validation guidance | `CONTRIBUTING.md`, `.github/workflows/ci.yml` |

## Acceptance criteria

1. A trusted client can submit a valid score event and receive an accepted response.
2. The same `Idempotency-Key` and canonical request replay the original result;
   reusing the key for a different request produces a conflict.
3. A duplicate business `event_id` cannot change a participant's score twice.
4. A leaderboard query returns stable, deterministic rank ordering and limits output
   to `1..100` entries.
5. Score event, projection, idempotency record, and outbox message commit or roll
   back together.
6. The queue delivers a signed webhook after commit, retries transient failures, and
   records permanent failures for review.
7. Tests cover success, retry/replay, duplicate event, validation, authentication,
   cache invalidation, and signature verification paths.
8. The Docker stack and CI definition describe the same dependencies and do not
   contain real secrets.
9. Documentation explains the non-obvious decisions, operational limits, and the
   deliberately excluded scope.

## Risks and mitigations

| Risk | Mitigation / status |
| --- | --- |
| Duplicate or concurrent delivery inflates scores | MySQL uniqueness constraints, transaction boundaries, idempotency fingerprints, and automated coverage. |
| A database commit and webhook delivery diverge | Transactional outbox; the worker performs delivery only after commit. |
| Cache becomes stale or Redis is unavailable | Redis is disposable; writes invalidate keys and MySQL remains authoritative. |
| Excessive scope makes the work look engineered for a demo rather than a product | Explicit exclusions and ADRs keep the modular monolith narrow. |
| Local machine lacks PHP/Docker | CI and Docker configuration provide the full runtime path; static review and Node tests can still run locally. |
| Webhook secrets or API keys leak | `.env` is ignored; only placeholders live in `.env.example`; docs prohibit logging secrets. |
| A configured webhook endpoint becomes an SSRF path | Endpoints are server-side configuration, not event input; production egress policy remains an operator requirement. |

## Validation strategy

1. **Static checks:** inspect JSON, YAML, Docker, routing, migrations, and PHP
   structure for consistency.
2. **Unit tests:** exercise pure fingerprint, signature, ranking, and policy logic.
3. **Feature tests:** cover HTTP authentication, validation, idempotency replay,
   conflicts, duplicate events, and deterministic rankings.
4. **Integration tests:** run against MySQL and Redis in CI, verifying transaction
   boundaries, cache behaviour, and queue semantics.
5. **Contract tests:** use the Node receiver to confirm valid/invalid HMAC signatures
   and retryable response handling.
6. **Compose validation:** render the Compose configuration before publication.
7. **Manual smoke test:** start the local stack, submit an example score event,
   fetch the ranking, and inspect the receiver log.

The final handoff records which checks were executed in the available environment
and which are documented but require Docker or CI.
