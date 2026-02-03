# Agent Build Instructions

## Project Setup
```bash
# Backend Laravel
cd backend
composer install
cp .env.example .env
php artisan key:generate

# Frontend React
cd ../frontend
npm install
```

## Running Tests
```bash
# Backend tests
cd backend
php artisan test

# Frontend tests
cd frontend
npm test
```

## Development Server
```bash
# Terminal 1: Laravel API
cd backend
php artisan serve --port=8000

# Terminal 2: React frontend
cd frontend
npm run dev
```

## Database
```bash
cd backend
php artisan migrate
php artisan db:seed
```

## Key Files for Mistral Migration
- backend/config/mistral.php - Configuration Mistral
- backend/app/Services/Ai/Traits/LlmClientTrait.php - Abstraction LLM
- backend/app/Services/Ai/RouterService.php - Détection sections
- backend/app/Services/Ai/Extractors/*.php - Extracteurs spécialisés
