# CRM Courtier AI - Architecture & Code Review Notes

## Tech Stack
- **Backend**: Laravel 12 + Octane (Swoole), PHP 8.3
- **Frontend**: React 19
- **Queue**: Redis-backed Laravel queues, named queues (audio, import, default)
- **Storage**: Scaleway S3 (MinIO local), local disks for temp/recordings/templates
- **Auth**: Sanctum (cookie-based SPA auth with CORS credentials)
- **AI/ML**: Whisper (STT), Pyannote (diarization), Mistral (LLM), OpenAI fallback
- **Infra**: Scaleway - 2 servers (app-server + worker-server), Docker Compose, Caddy reverse proxy
- **Target**: 80 users (20 cabinets x 4 users)

## Critical Pattern: env() in Laravel with config:cache
- `docker-entrypoint.prod.sh` runs `php artisan config:cache` on boot
- After `config:cache`, `env()` returns `null` outside config files
- `CorsMiddleware.php` and `bootstrap/app.php` both call `env('CORS_ALLOWED_ORIGINS')` directly
- This is a **production-breaking bug** - CORS will fail silently in production
- Solution: Use `config('cors.allowed_origins')` or create a dedicated config key

## CORS Architecture (3 layers)
1. `config/cors.php` - Laravel's built-in CORS handling (via fruitcake/laravel-cors or framework)
2. `CorsMiddleware.php` - Custom middleware prepended to all requests
3. `bootstrap/app.php` - Exception handler CORS (for error responses)
- All three read from `CORS_ALLOWED_ORIGINS` env var
- Potential redundancy between layers 1 and 2

## Queue Architecture
- 4 jobs total, all have explicit `$queue` property
- Audio: `ProcessAudioRecording` -> queue 'audio' (3 workers)
- Import: `AnalyzeImportFileJob`, `ProcessImportSessionJob`, `ProcessImportBatchJob` -> queue 'import' (1 worker)
- Workers fall back to 'default' queue
- `stop_grace_period: 310s` correctly > `timeout: 300s`

## Docker Compose Structure
- `deploy/app-server/docker-compose.yml`: caddy + backend (Octane) + gotenberg
- `deploy/worker-server/docker-compose.yml`: 3 audio-workers + 1 import-worker + scheduler
- Shared `hf_cache` volume for HuggingFace model persistence

## Code Style
- French comments and log messages with emojis
- Services instantiated via `new` in jobs (not always DI)
- `ProcessAudioRecording` is a large monolithic job (~720 lines)
