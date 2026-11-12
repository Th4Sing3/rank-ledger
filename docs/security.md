# Security notes

Rank Ledger is a reference implementation, not a claim that a deployment is
production ready. The code and local Compose stack demonstrate the following
baseline controls; a real deployment still needs threat modelling and operational
ownership.

## Implemented boundaries

### API keys

- Real deployments must send `X-API-Key` over HTTPS. The local Compose demo uses
  HTTP only inside a development environment.
- The database stores a SHA-256 digest, never a plain-text API key.
- Keys are never placed in API responses or logs.
- A production rotation endpoint/process belongs outside this focused MVP.

### Input and data integrity

- Form requests validate identifiers, timestamps, point ranges, and metadata shape
  before application code runs.
- MySQL unique indexes enforce idempotency and business-event uniqueness even when
  two application processes race.
- Score mutations, event append, outbox insert, and idempotent response persistence
  are enclosed in one transaction.
- Scores are integers; no float or monetary arithmetic exists in this application.

### Webhooks

- The sender signs the raw body plus a timestamp with HMAC-SHA256.
- The Node receiver uses `timingSafeEqual` after validating comparable buffer
  lengths.
- Retries are bounded. A permanent `4xx` does not become an infinite retry loop.
- Webhook URLs are configured server-side, never accepted from an event payload.

## Deployment controls deliberately left to the operator

- TLS termination and certificate rotation;
- a secrets manager instead of `.env` files;
- outbound allowlists / SSRF controls for webhook destinations;
- WAF, network-layer request-size limits, and API-key rotation;
- encrypted backups, retention policy, access logging, and incident response;
- alerting on failed jobs, outbox lag, database errors, and Redis availability;
- patch management for PHP, Composer dependencies, MySQL, Redis, Node, and images.

## Privacy and logging

The example metadata is intentionally non-sensitive. Producers should not place
raw e-mail addresses, payment details, access tokens, or other personal data in an
event's metadata. Application logs should retain request IDs and safe identifiers,
not raw headers or full payloads by default.

## Reporting a vulnerability

Do not open a public issue for a potential security vulnerability. Contact the
repository owner privately with a minimal reproduction and affected version.
