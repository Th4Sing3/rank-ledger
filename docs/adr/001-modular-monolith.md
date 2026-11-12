# ADR 001: Start with a modular monolith

- **Status:** Accepted
- **Date:** 2026-11-11

## Context

Submitting a score event must append an audit row, update a projection, preserve an
idempotent response, and create an outbox message atomically. The system has a
small number of well-defined components and no demonstrated need for independent
deployment.

## Decision

Build a single Laravel application with internal module boundaries. Use MySQL for
the transactional core, Redis for cache and jobs, and a separate Node receiver only
as an integration example.

## Consequences

- The main write path has one clear transaction and is easy to test locally.
- Deployment and observability are simpler than a distributed system.
- Independent horizontal scaling is deferred. If real throughput demands it, split
  based on observed bottlenecks and an explicit ownership boundary.
