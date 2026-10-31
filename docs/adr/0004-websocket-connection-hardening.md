# ADR 0004: Harden the WebSocket gateway with short lived, renewable credentials

## Status

Accepted

## Context

Phase 3 shipped a gateway that authenticated once, on connect. That left four gaps:

1. A leaked connection token kept a socket alive for as long as the socket stayed open, because nothing re-checked expiry after the handshake.
2. One account could open an unlimited number of sockets, so a single client could exhaust the instance.
3. Inbound frames had no rate limit: a client could flood subscribe, typing, and receipt frames.
4. Broker events were fanned out purely by NATS subject, so a mis-published event would be delivered to every subscriber of the wrong conversation.

The gateway also closed sockets abruptly on shutdown, which clients saw as a transport failure and retried with backoff instead of reconnecting immediately.

## Decision

1. The Laravel issued credential stays short lived (`GATEWAY_TOKEN_TTL`, default 300s). The gateway refuses to keep a socket past `exp`, and closes it with `1008 token expired`.
2. Clients renew in place: `connection.authenticate` is accepted on an authenticated socket when the token subject matches the existing user. The expiry timer is reset and `connection.ready` is re-sent with the new `connection_token_expires_at`. A renewal for a different subject is rejected as a policy violation.
3. `GATEWAY_MAX_CONNECTIONS_PER_USER` caps sockets per account. The check runs before the connection is added to the registry.
4. `GATEWAY_INBOUND_RATE_PER_SECOND` and `GATEWAY_INBOUND_BURST` install a per connection token bucket on the read loop. Excess frames are dropped and counted in `gateway_inbound_throttled_total`.
5. Broker subjects are cross-checked against the envelope `conversation_id` (and the typing payload). Mismatches are dropped and counted in `gateway_events_dropped_total{reason="conversation_mismatch"}`.
6. Shutdown drains connections with `1001`, and clients reset their backoff on `1001` so a rolling restart reconnects immediately.

## Consequences

- The frontend must renew proactively. `apps/web/resources/js/lib/realtime.ts` schedules renewal at `min(60s, max(5s, ttl * 0.2))` before expiry and falls back to a full reconnect if renewal fails.
- Every account now has a hard socket budget. Legitimate multi-tab and multi-device usage has to fit inside `GATEWAY_MAX_CONNECTIONS_PER_USER`, which defaults to 10.
- Dropped inbound frames are silent by design: a chatty client loses the surplus frames instead of being disconnected, and the counter exposes the behavior.
- Cross-checking subjects costs one comparison per event and prevents an entire class of cross-conversation leaks.
- `1001` is now semantically meaningful to clients, so retry policy depends on close codes and not only on transport errors.
