# SDMastery — Backend CLAUDE.md

> This file is the Claude Code context for the Laravel backend.
> Read this file AND `../CONSTITUTION.md` at the start of every session.

---

## Project

SDMastery backend — Laravel 11 REST API serving the SDMastery SPA frontend.

**Root:** `BackEnd/` (this directory)
**Planning files:** `../` (project root)

---

## Quick Reference

### Run the stack
```bash
# API server
php artisan serve --port=8000

# Queue worker (keep running in separate terminal)
php artisan queue:work --sleep=3 --tries=3

# Clear and warm caches
php artisan cache:clear
php artisan config:cache

# Run tests
php artisan test
php artisan test --filter=AuthTest

# RAG ingestion
php artisan rag:ingest --file=storage/rag/books/ddia.pdf
```

### Key directories
```
app/
  Http/
    Controllers/Api/V1/   ← all controllers here
    Requests/             ← Form Request validation classes
    Resources/            ← API Resource transformers
  Services/
    Auth/                 ← AuthService
    Evaluation/           ← AttemptEvaluationService, EvaluationPrompts
    LLM/                  ← OllamaProvider, OpenAIProvider, GeminiProvider, GrokProvider, LLMProviderFactory
    RAG/                  ← ChromaClient, EmbeddingService, RagRetrievalService
    Progress/             ← UserProgressService
    Gamification/         ← BadgeService, StreakService
    Analytics/            ← AnalyticsService
  Jobs/
    EvaluateAttemptJob
    SendVerificationEmailJob
    SendPasswordResetEmailJob
    StoreModelAnswerInRagJob
    IngestBookChunkJob
  Models/                 ← All Eloquent models
  Contracts/              ← Interfaces (LLMProviderInterface)
  DTO/                    ← Data Transfer Objects (EvaluationResult, RagContext)
  Exceptions/             ← Custom exceptions (ChromaException, LLMException)
routes/
  api.php                 ← All routes under /api/v1/
tests/
  Feature/                ← HTTP-level tests
  Unit/                   ← Service/helper unit tests
```

---

## Architecture Rules (summary — full version in CONSTITUTION.md)

1. Controllers only: validate → call service → return ApiResponse
2. All business logic in Services
3. All LLM calls in queue jobs, never in HTTP requests
4. All queries scoped by `auth()->id()` for user data
5. API keys stored with `encrypted` cast
6. Use Laravel API Resources, not `->toArray()`
7. Every route has a test

---

## Response Format

Always use this format:

```php
// Success
return response()->json([
    'success' => true,
    'data' => $resource,
    'message' => 'Optional message',
]);

// Error
return response()->json([
    'success' => false,
    'message' => 'What went wrong',
    'errors' => $errors, // optional
], $statusCode);
```

---

## Current Phase

Update this line when starting a phase:
**Active phase:** BE-1 Foundation (Auth, Models, Migrations, Seeders)

**Branch:** phase/be-1-foundation

---

## Environment

```
OLLAMA_BASE_URL=http://192.168.1.113:11434
CHROMA_BASE_URL=http://localhost:8002
QUEUE_CONNECTION=database
CACHE_DRIVER=file
MAIL_MAILER=log
SANCTUM_STATEFUL_DOMAINS=localhost:5174
FRONTEND_URL=http://localhost:5174
```

ChromaDB must be running before RAG features work.
Ollama must be running with `llama3:latest`, `mistral:latest`, and `nomic-embed-text` pulled.

---

## Phase Checklist Before Merge

- [ ] All new routes have Feature tests
- [ ] All new services have Unit tests
- [ ] No `dd()` or `dump()` in code
- [ ] .env.example updated with any new vars
- [ ] Migration runs clean on fresh DB
- [ ] Queue jobs handle exceptions gracefully (try/catch, fail with log)
- [ ] New API keys are encrypted in storage
- [ ] All user data queries scoped by auth()->id()
