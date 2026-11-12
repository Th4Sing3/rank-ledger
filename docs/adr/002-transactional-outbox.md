# ADR 002: Use a transactional outbox for webhooks

- **Status:** Accepted
- **Date:** 2026-11-11

## Context

Sending a webhook inside the request transaction creates an unsafe gap: the remote
consumer may receive a notification even if the database rolls back, or the
database may commit after the HTTP call fails and leave the caller unsure what
happened.

## Decision

Insert an `outbox_messages` row in the same MySQL transaction that records the
score event and projection. After commit, queue a job that delivers pending
messages. Store delivery attempts and use bounded retry/backoff for transient
failures.

## Consequences

- Delivery becomes at-least-once; receivers must deduplicate with event ID.
- A committed score is never silently lost because a process died before an HTTP
  call.
- A worker and backlog monitoring are required, but their responsibilities are
  narrow and observable.
