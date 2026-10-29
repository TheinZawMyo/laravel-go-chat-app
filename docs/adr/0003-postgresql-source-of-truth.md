# ADR 0003: PostgreSQL is the source of truth

## Status

Accepted

## Context

Message history, conversation membership, and authorization data must survive service restarts and broker outages. Redis and NATS are operational dependencies, not durable product data stores.

## Decision

Store users, conversations, memberships, messages, receipts, notifications, and outbox events in PostgreSQL.

- Message creation and outbox insertion happen in one transaction.
- Per-conversation `seq` values are assigned transactionally.
- Redis holds sessions, cache, queues, presence leases, and rate-limit counters.
- NATS JetStream transports events; it does not replace history.

## Consequences

- Clients can always recover missing events via the Laravel API.
- Broker or gateway outages delay delivery but do not lose committed messages.
- Backup and restore procedures target PostgreSQL first.
- Redis eviction must never be treated as data loss for messages.
