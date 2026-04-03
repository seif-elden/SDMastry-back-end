# SDMastery Backend

Backend API for SDMastery, built with Laravel.

This service handles:

- Authentication and profile endpoints.
- Topic roadmap data.
- Attempt submission and evaluation lifecycle.
- Chat over completed attempts.
- Progress, badges, and analytics data.
- RAG-backed context retrieval for evaluation/chat flows.

## Local Run

1. Install dependencies:
	- `composer install`
2. Create environment file:
	- `cp .env.example .env`
3. Generate app key:
	- `php artisan key:generate`
4. Prepare SQLite:
	- `mkdir -p database && touch database/database.sqlite`
5. Run migrations:
	- `php artisan migrate`
6. Start API:
	- `php artisan serve`

Optional queue worker:

- `php artisan queue:work`

## To Do

- [ ] Add clearer API endpoint documentation with example requests/responses.
- [ ] Add sequence diagram for attempt -> evaluation -> progress -> chat flow.
- [ ] Expand integration tests for chat + evaluation edge cases.
- [ ] Add structured logging fields for tracing request/job flows.
- [ ] Add CI check for migrations and feature test smoke run.
