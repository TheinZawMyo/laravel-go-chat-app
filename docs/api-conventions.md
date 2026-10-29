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
