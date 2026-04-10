.PHONY: fix lint ci test

fix: ## Auto-corriger Pint + ESLint
	cd backend && ./vendor/bin/pint
	cd frontend && npx eslint . --fix

lint: ## Vérifier sans modifier
	cd backend && ./vendor/bin/pint --test
	cd frontend && npm run lint
	cd frontend && npx tsc --noEmit

test: ## Tests backend (nécessite MySQL + Redis)
	cd backend && php artisan test

ci: fix lint test ## Simulation complète du CI en local
