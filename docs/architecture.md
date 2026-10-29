# Architecture

The system is split into three deployable applications that share PostgreSQL, Redis, and NATS JetStream.

```text
Vue 3 + Inertia + Tailwind + Pinia
              |
        HTTPS / WSS
              |
          Nginx / TLS
          /         \
 Laravel (apps/web)  Go gateway (apps/gateway)
          |                    |
          |                    +--- Redis (presence, routing, rate limits)
          |
     PostgreSQL (source of truth)
          |
   outbox_events
          |
     Go worker (apps/worker)
          |
     NATS JetStream (durable broker)
          |
   gateway fan-out / notifications
```

## Services

| Service | Path | Port | Responsibility |
|---|---|---|---|
| Web application | `apps/web` | 8000 | Authentication, authorization, profiles, conversations, message persistence, history, Inertia pages, `/api/v1` |
| Gateway | `apps/gateway` | 8081 | WebSocket lifecycle, connection registry, subscriptions, live delivery, health, metrics |
| Worker | `apps/worker` | 8082 | Outbox dispatch, event publishing, fan-out, notifications, cleanup |
| PostgreSQL | — | 5432 | Permanent data and transactional outbox |
| Redis | — | 6379 | Sessions, cache, queues, presence leases, routing, rate-limit counters |
| NATS JetStream | — | 4222 | Durable, retryable event delivery |
| MinIO | — | 9000 | Media and attachment storage |

## Rules

1. Laravel owns business logic and permanent data.
2. The gateway never bypasses Laravel authorization or persistence.
3. The worker consumes durable events and must be idempotent.
4. PostgreSQL is the source of truth; Redis is ephemeral.
5. Message creation happens over HTTPS in Laravel, not over the WebSocket.
6. Every conversation operation checks membership.
7. Message history uses cursor pagination ordered by per-conversation `seq`.
8. Durable delivery is at-least-once; clients deduplicate by `event_id`.

## Health endpoints

| Endpoint | Purpose |
|---|---|
| `GET :8081/healthz` | Gateway liveness |
| `GET :8081/readyz` | Gateway readiness (PostgreSQL + Redis) |
| `GET :8081/metrics` | Prometheus metrics |
| `GET :8082/healthz` | Worker liveness |
| `GET :8082/readyz` | Worker readiness (PostgreSQL + Redis + NATS) |
| `GET :8082/metrics` | Prometheus metrics |

## Configuration

Each service reads configuration from environment variables. The Go services also load a local `.env` file when present. See the `.env.example` file in each application.
