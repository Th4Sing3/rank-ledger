# ADR 003: Do not build a dashboard for the MVP

- **Status:** Accepted
- **Date:** 2026-11-11

## Context

A frontend could be visually appealing, but the product question under test is
whether the backend accepts and exposes correct leaderboard state under retries and
concurrency.

## Decision

Provide a documented HTTP API, curl examples, automated tests, and a Node webhook
receiver. Do not add a Vue dashboard, authentication UI, or real-time client.

## Consequences

- More effort goes to verifiable API correctness and operational behaviour.
- A frontend can be added later against a stable contract if a user workflow proves
  its value.
- This is a deliberate YAGNI decision, not an omission hidden by scaffolding.
