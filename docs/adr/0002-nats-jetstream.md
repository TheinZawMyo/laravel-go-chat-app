# ADR 0002: NATS JetStream as the durable broker

## Status

Accepted

## Context

The implementation plan requires a durable broker between the transactional outbox and the gateway. The candidates were RabbitMQ and NATS JetStream. Redis Pub/Sub alone is not durable.

## Decision

Use NATS JetStream.

- Single lightweight binary, available as a single binary.
- Persistent streams with at-least-once delivery and acknowledgment.
- Work-queue and interest-based retention support worker fan-out and consumer groups.
- Simple client libraries for Go and PHP.

## Consequences

- Consumers must be idempotent and deduplicate by stable event IDs.
- Stream, consumer, retention, and dead-letter configuration must be defined as code or documented operations.
- Cross-instance gateway routing can later leverage NATS subjects.
