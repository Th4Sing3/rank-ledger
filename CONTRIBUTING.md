# Contributing

Thanks for improving Rank Ledger. This repository values small, reviewable changes
with explicit behaviour over speculative architecture.

## Before opening a pull request

1. Describe the user or operational problem, not only the implementation.
2. Add or update a test that would fail without the change.
3. Run the relevant commands:

   ```bash
   composer validate --strict
   php artisan test
   vendor/bin/pint --test
   npm --prefix examples/webhook-receiver test
   ```

4. Update the API documentation if a public contract changes.
5. Add an ADR for a consequential, durable architecture decision. Do not create an
   ADR for local refactors or naming changes.

## Conventions

- Keep controllers thin: HTTP in, JSON response out.
- Validate input at the boundary; do not scatter validation through jobs and models.
- Prefer MySQL constraints for business invariants that must survive concurrency.
- Treat Redis as an optimisation and transport, never the final authority for a
  score or an idempotency decision.
- Add comments for a non-obvious **why**, not to narrate obvious PHP syntax.
- Never commit `.env`, API keys, webhook secrets, production exports, or personal
  data.

## Pull-request checklist

- [ ] Tests cover the normal path and the failure/retry path when relevant.
- [ ] No new duplicate implementation of a canonical rule exists.
- [ ] Logging is safe and secrets are absent.
- [ ] Documentation and examples remain truthful.
- [ ] The change does not add a framework, abstraction, or service without a
      demonstrated requirement.
