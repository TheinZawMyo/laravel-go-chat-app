# ADR 0001: Monorepo with a Laravel modular monolith

## Status

Accepted

## Context

The initial scale is 1,000–10,000 registered users. The product needs durable messaging, realtime delivery, and background processing, but not a microservice fleet.

## Decision

Use one repository containing:

- `apps/web` — Laravel application with Inertia + Vue 3 frontend
- `apps/gateway` — Go WebSocket gateway
- `apps/worker` — Go background worker

Laravel remains the single source of business logic and persistence. The Go services are deployed separately because their runtime and scaling characteristics differ, not because the domain is split into independent services.

## Consequences

- One checkout, one CI pipeline, one contract document.
- Shared contracts must be kept in sync manually until generated or shared packages are introduced.
- The gateway and worker can be scaled independently.
- Splitting Laravel into services is deferred until measurements justify it.
