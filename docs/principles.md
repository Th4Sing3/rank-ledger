# Design principles, applied

The project uses these principles as constraints on concrete decisions, not as labels
added after the code exists.

| Principle | Where it appears | Why it is useful |
| --- | --- | --- |
| **DRY** | `RequestFingerprint` is the single canonicalisation and hashing path for idempotency checks. `WebhookSigner` owns the sender's HMAC format. | A second JSON normaliser or ad hoc signature builder would eventually disagree about what the same request or message means. |
| **KISS** | One Laravel application, MySQL, Redis, and a focused worker own the workflow. | The consistency problem is visible in one transaction. Kafka, CQRS infrastructure, and independently deployed services would add moving parts before there is evidence they solve a real problem. |
| **YAGNI** | No Vue dashboard, user accounts, payment logic, tournament engine, generic rules framework, or multi-region topology. | None is needed to receive a score event safely or show a ranked projection. |
| **Single Responsibility** | Middleware authenticates; form requests validate; controllers map HTTP; `SubmitScoreEvent` coordinates the write; services fingerprint, snapshot, or sign; jobs perform asynchronous I/O. | Each class has a narrow reason to change. |
| **Open / Closed** | The API keeps score intake separate from its outbound event contract. New fields can be added to the webhook body without changing the transaction choreography. | Integration payloads can evolve deliberately instead of turning the controller into a collection of producer-specific branches. |
| **Liskov Substitution** | The application does not invent a class hierarchy for its domain models. It relies on Laravel's established framework contracts where substitution is already meaningful. | Avoiding fake inheritance is more useful than creating type trees merely to mention SOLID. |
| **Interface Segregation** | `RequestFingerprint` and `WebhookSigner` expose only their specific operation. | Callers do not depend on a broad utility or repository interface they do not need. |
| **Dependency Inversion** | Controllers receive actions/services through Laravel's container; the action receives its fingerprint and snapshot collaborators through its constructor. | The application layer depends on focused behaviour rather than duplicating it in HTTP code. |

## A practical reading of SOLID

SOLID does not require an interface around every Eloquent model or a repository for
every query. Eloquent is Laravel's native persistence boundary and keeping it direct
makes this small service easier to read. Abstractions are justified only where they
remove meaningful duplication or isolate a volatile concern.

The Node receiver intentionally implements the HMAC contract independently rather
than sharing application code. It acts as an external consumer would: the test proves
the wire protocol, not that two callers imported the same helper.

## Why a modular monolith

The important write is transactional: event audit, current entry, idempotency
response, and outbox row must agree. A modular monolith keeps that invariant
inspectable and testable. If measured load eventually demands it, the recorded
events provide a natural starting point for a separate projection process. Until
then, keeping the workflow together is the simpler design.

Related choices are recorded in the [architecture decisions](adr/).

