---
name: deploy-prep
description: Checklist pre-deploiement. Verifier que tout est pret avant un deploiement en production.
disable-model-invocation: true
allowed-tools: Bash, Read, Grep, Glob
---

# Checklist pre-deploiement

## 1. Code

- [ ] Toutes les modifications sont commitees
- [ ] Pas de `dd()`, `dump()`, `console.log()` de debug
- [ ] Pas de TODO critiques non resolus

Verification :
```bash
cd /Users/florian/Documents/projet-courtier/crm-ai-copie
git status
grep -rn "dd(" backend/app/ --include="*.php" | grep -v "vendor"
grep -rn "dump(" backend/app/ --include="*.php" | grep -v "vendor"
grep -rn "console\.log" frontend/src/ --include="*.tsx" --include="*.ts"
```

## 2. Migrations

- [ ] Toutes les migrations sont executees
- [ ] Les seeders necessaires sont a jour

```bash
docker exec laravel_app php artisan migrate:status
```

## 3. Environment

- [ ] Variables .env de production configurees
- [ ] `APP_DEBUG=false` en production
- [ ] `APP_ENV=production`
- [ ] Cles API (OpenAI, HuggingFace) configurees
- [ ] S3 (AWS) configure avec les bonnes credentials
- [ ] `FILESYSTEM_DISK=s3`

## 4. Storage S3

- [ ] Bucket S3 cree en production
- [ ] Credentials AWS configurees
- [ ] `AWS_USE_PATH_STYLE_ENDPOINT=false` (AWS natif, pas MinIO)
- [ ] Fichiers existants migres (`php artisan storage:migrate-to-s3`)

## 5. Build frontend

```bash
cd frontend && npm run build
```

- [ ] Build sans erreur
- [ ] Pas de warnings TypeScript critiques

## 6. Queues et caches

- [ ] Redis accessible en production
- [ ] Queue worker configure (Supervisor ou similaire)
- [ ] Cache config : `php artisan config:cache` (attention aux closures)

## 7. Securite

- [ ] Pas de secrets dans le code source
- [ ] CORS configure correctement
- [ ] Rate limiting actif
- [ ] HTTPS force

## 8. RGPD

- [ ] Politique de retention des donnees configuree
- [ ] Cron pour `php artisan audio:purge-old`
- [ ] Consentements traces pour les imports
