# SDMastery Backend

Laravel backend for SDMastery.

This backend is intentionally optimized for local development and iteration speed. It is minimal by design so you can test learning flows quickly, then harden and scale in later phases.

## Why This Project Is Useful

SDMastery is built to help learners practice software design/system design topics with fast feedback loops:

- Topic roadmap and progression tracking.
- Attempt submission and automated evaluation.
- Guided chat follow-up per attempt.
- RAG-backed contextual feedback.
- Badge and streak mechanics for engagement.

## End-to-End Flow

1. User authenticates with Sanctum token.
2. User opens roadmap and receives per-topic progress.
3. User submits an attempt for a topic.
4. `EvaluateAttemptJob` runs async evaluation.
5. Evaluation result is saved (`score`, `passed`, strengths/weaknesses, notes).
6. Progress tracking updates `user_topic_progress`.
7. Chat session is created/seeded from evaluation notes.
8. User asks follow-up questions in chat (topic-guarded tutoring).

## Main Backend Components

- Auth and profile endpoints: login/register/me/logout.
- Topic and attempt endpoints.
- Queue jobs for evaluation and RAG indexing.
- Chat service with provider abstraction.
- Progress, analytics, badges/streak services.
- RAG retrieval for contextual evaluation support.

## Local-First Scope

This repository currently targets local usage:

- SQLite by default.
- Minimal infra assumptions.
- Fast setup over production hardening.
- Basic queue/cache setup with optional Redis.

For production, add stronger observability, deployment automation, backup strategy, and stricter security/runtime controls.

## Run Locally (Without Docker)

1. Install dependencies:
   - `composer install`
2. Create env:
   - `cp .env.example .env`
3. Generate app key:
   - `php artisan key:generate`
4. Prepare SQLite database:
   - `mkdir -p database && touch database/database.sqlite`
5. Run migrations:
   - `php artisan migrate`
6. Start server:
   - `php artisan serve`
7. Optional queue worker:
   - `php artisan queue:work`

## Docker + Redis (Local Minimal Stack)

Added local docker files:

- `Dockerfile`
- `docker-compose.yml`
- `.dockerignore`

### What the compose stack runs

- `app` (Laravel API)
- `queue` (Laravel queue worker)
- `redis` (cache/queue/session backend)

### Start stack

- `docker compose up --build`

API will be available on `http://localhost:8000`.

### Redis usage

The local profile is configured to use Redis for:

- `CACHE_STORE=redis`
- `QUEUE_CONNECTION=redis`
- `SESSION_DRIVER=redis`

This improves local parity with production-style queue/cache behavior while staying lightweight.

## RAG and Pipeline Enhancements (Recommended Next)

### RAG improvements

- Add hybrid retrieval (vector + keyword).
- Add re-ranking step for top-k chunks.
- Add source quality scoring and deduplication.
- Version embeddings by model and schema.
- Add retrieval latency and relevance telemetry.

### Evaluation pipeline improvements

- Add idempotency keys for jobs.
- Add dead-letter strategy and retry policies by failure type.
- Add structured event logs and trace IDs per attempt.
- Add model/provider fallback routing policies.
- Add offline regression evaluation suite for scoring consistency.

## Database Alternatives

Current default is SQLite for minimal local setup. Alternatives:

- PostgreSQL: strong default for production workloads.
- MySQL/MariaDB: good ecosystem support and familiarity.
- SQLite: best for local/dev and test speed.

Recommended path:

- Local: SQLite or Dockerized Postgres.
- Shared staging/prod: Postgres + Redis.

## TODO

- [ ] Add health endpoints for queue/redis/rag dependencies.
- [ ] Add API rate-limit observability dashboards.
- [ ] Add migration checks in CI.
- [ ] Add seed profiles for demo/test users.
- [ ] Add structured logging (JSON) for jobs and chat flows.
- [ ] Add integration tests for Docker profile (`app + queue + redis`).
- [ ] Add production-ready deployment docs.

## Notes

- This backend is intentionally minimal for local use and rapid experimentation.
- Keep architecture docs in repo root aligned with backend implementation as flows evolve.
