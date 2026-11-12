# API contract

All endpoints are versioned under `/api/v1` and require a trusted producer key:

```http
X-API-Key: <client secret>
```

The service stores only the SHA-256 digest of a key. Keys identify a producer and
must be sent over TLS in a real deployment.

## Submit a score event

```http
POST /api/v1/leaderboards/{leaderboard}/events
Idempotency-Key: <1..128 character opaque key>
Content-Type: application/json
X-API-Key: <client secret>
```

`{leaderboard}` is the configured external identifier for the API client. Treat
it as case-sensitive at the HTTP boundary.

### Request body

```json
{
  "event_id": "round_01JMY9P5QKAZMHZJ8HBP3Z2TZG",
  "participant_id": "player_ada",
  "points": 125,
  "occurred_at": "2026-11-11T18:00:00Z",
  "metadata": {
    "source": "demo",
    "round_reference": "round-42"
  }
}
```

| Field | Required | Rules |
| --- | --- | --- |
| `event_id` | Yes | Producer event identifier, unique within a leaderboard. Letters, digits, `.`, `_`, `:`, and `-` are accepted. |
| `participant_id` | Yes | Stable producer participant identifier using the same character set. |
| `points` | Yes | Non-zero signed integer between -1,000,000 and 1,000,000. It has no currency or financial meaning. |
| `occurred_at` | Yes | Date timestamp. RFC 3339 / ISO 8601 input is expected and stored in UTC. A value more than five minutes in the future is rejected. |
| `metadata` | No | Flat object with at most 20 string values, each up to 1,024 characters. It is recorded but not interpreted. |

### Created response

```http
HTTP/1.1 201 Created
Idempotent-Replay: false
Content-Type: application/json
```

```json
{
  "change_status": "created",
  "event": {
    "id": "round_01JMY9P5QKAZMHZJ8HBP3Z2TZG",
    "participant_id": "player_ada",
    "points": 125,
    "occurred_at": "2026-11-11T18:00:00+00:00"
  },
  "entry": {
    "participant_id": "player_ada",
    "score": 125,
    "event_count": 1,
    "last_event_at": "2026-11-11T18:00:00+00:00"
  }
}
```

### Retry and duplicate semantics

| Situation | HTTP status | Result |
| --- | --- | --- |
| First request | `201` | Event is written, projection changes, and an outbox message is created. |
| Same idempotency key and canonical request | Original status | The saved response body is replayed with `Idempotent-Replay: true`; no second side effect occurs. |
| Same idempotency key, different request | `409` | The request is ambiguous and is rejected. Use a new key. |
| New idempotency key, same `event_id` and event data | `200` | `change_status: "not_modified"`; the score does not change. |
| New idempotency key, same `event_id` and different data | `409` | The event identity was reused inconsistently. |
| Invalid body or missing idempotency key | `422` | Correct the input; retrying unchanged data will not help. |
| Missing or invalid API key | `401` | Request is unauthenticated. |
| Unknown leaderboard | `404` | Configure the leaderboard out of band. |

## Get a leaderboard

```http
GET /api/v1/leaderboards/{leaderboard}?limit=10
X-API-Key: <client secret>
```

`limit` defaults to `10` and accepts values from `1` through `100`. Entries
are ordered deterministically: descending score, then the oldest last-activity
timestamp, then participant identifier.

```json
{
  "leaderboard": {
    "id": "daily-demo",
    "name": "Daily demo leaderboard"
  },
  "entries": [
    {
      "rank": 1,
      "participant_id": "player_ada",
      "score": 125,
      "event_count": 1,
      "last_event_at": "2026-11-11T18:00:00+00:00"
    }
  ]
}
```

The result may come from the short-lived Redis cache. MySQL remains the authority
for all score state.

## Webhook contract

After a committed score event, Rank Ledger publishes an outbox message and delivers
it asynchronously to the API client's configured webhook URL.

```http
X-Rank-Ledger-Event: round_01JMY9P5QKAZMHZJ8HBP3Z2TZG
X-Rank-Ledger-Delivery: 018f0e9c-20d9-7d67-9fc6-8b2f50e9c44d
X-Rank-Ledger-Signature: t=1794420001,v1=<hex-hmac>
Content-Type: application/json
```

The body has a stable event ID for receiver-side deduplication:

```json
{
  "id": "round_01JMY9P5QKAZMHZJ8HBP3Z2TZG",
  "type": "leaderboard.score_recorded",
  "occurred_at": "2026-11-11T18:00:00+00:00",
  "data": {
    "leaderboard_id": "daily-demo",
    "participant_id": "player_ada",
    "points": 125,
    "score": 125
  }
}
```

The `v1` value is `HMAC-SHA256(timestamp + "." + rawRequestBody, webhookSecret)`.
Recipients must verify the timestamp against a short tolerance, compare the HMAC in
constant time, and deduplicate business effects by the body `id` or
`X-Rank-Ledger-Event` header. The Node example implements that exact signature
format.

A `2xx` response acknowledges receipt. Network failures, `429`, and `5xx`
responses are retried with bounded backoff. Other `4xx` responses are recorded
as permanent delivery failures.

## Error responses

Authentication and domain conflicts return a concise message:

```json
{
  "message": "This Idempotency-Key was already used with a different request."
}
```

Laravel validation errors also include an `errors` object keyed by field name. No
response exposes database details, API-key material, webhook secrets, or traces.

