# API and event conventions

## HTTP

- JSON endpoints live under `/api/v1`.
- Browser page navigation uses Inertia; the same Laravel services back both paths.
- Session authentication with secure, HttpOnly, SameSite cookies and CSRF protection.
- Resource authorization is enforced server-side on every request.
- List endpoints use cursor pagination.

### Error shape

```json
{
  "message": "Human readable error",
  "errors": {
    "field": ["Validation message"]
  }
}
```

Validation errors return `422`. Authentication failures return `401`. Authorization failures return `403`. Missing resources return `404`. Rate limits return `429`.

## Message history

- Messages are ordered by a per-conversation `seq` assigned transactionally.
- Fetch older messages with `?before_seq=<seq>&limit=<n>`.
- Clients store the highest contiguous `seq` they have confirmed.
- Message creation is idempotent on `(sender_id, client_message_id)`.

## WebSocket events

Envelope:

```json
{
  "version": 1,
  "event_id": "event-uuid",
  "type": "message.created",
  "conversation_id": "conversation-uuid",
  "seq": 105,
  "occurred_at": "2026-10-29T00:30:00Z",
  "data": {}
}
```

Client to gateway:

- `connection.authenticate`
- `conversation.subscribe`
- `conversation.unsubscribe`
- `typing.start`
- `typing.stop`
- `receipt.delivered`
- `receipt.read`
- `ping`

Gateway to client:

- `connection.ready`
- `message.created`
- `message.updated`
- `message.deleted`
- `message.reaction.updated`
- `receipt.updated`
- `typing.updated`
- `presence.updated`
- `conversation.updated`
- `sync.required`

Rules:

- `event_id` is stable for deduplication.
- `seq` orders messages within one conversation.
- Edits do not create new messages.
- Clients reconnect with exponential backoff and jitter.
- Subscriptions are reauthorized after reconnect and membership changes.
- Clients recover missed messages through the Laravel API from their last confirmed `seq`.
- The gateway closes a socket when its credential expires; clients renew in place with another `connection.authenticate` frame.
- Typing and receipt frames require an existing subscription, and receipts must carry a positive `seq`.
- The complete frame contract, limits, and close codes are documented in [`websocket-protocol.md`](websocket-protocol.md).

## Authorization conventions

- Every controller action that touches a user-owned resource authorizes through a policy (`Gate::authorize` / `$this->authorize`).
- Policies live in `apps/web/app/Policies` and are auto-discovered for their models.
- `UserPolicy` allows authenticated users to view profiles, and only the owner to update or delete the account.
- `UserDevicePolicy` restricts device listing and revocation to the owning user.
- Form Requests own validation; controllers never trust raw request input.
- Inactive accounts are rejected during password login and logged out by `EnsureAccountIsActive`.
- Authenticated requests update `last_seen_at` at most once per minute.

## Phase 1 endpoints

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/api/v1/me` | Current user profile |
| GET | `/api/v1/users/{username}` | Public profile lookup by username |
| GET | `/api/v1/devices` | List the current user's devices |
| DELETE | `/api/v1/devices/{id}` | Revoke a device |

Usernames are unique, 3-30 characters, ASCII letters/numbers/dashes/underscores, and stored lowercase.
