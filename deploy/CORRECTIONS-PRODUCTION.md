# Corrections Finales — Deploiement Production CRM Courtier AI

## Contexte

Les fichiers de deploiement principaux ont ete crees (Dockerfile.prod, docker-compose app/worker, Caddyfile, .env.production.example, CI/CD). L'audit approfondi du code STT/LLM et le dimensionnement pour **20 cabinets x 4 users = 80 utilisateurs** ont revele des correctifs critiques.

**Probleme de dimensionnement majeur** : Avec 1 seul queue worker et un timeout de 300s par job audio, 10 uploads simultanes prendraient **33 minutes** a traiter. Le worker DEV1-M (4 GB) est sous-dimensionne pour les modeles IA (~2-4 GB par worker).

---

## Fichiers modifies

| # | Fichier | Action |
|---|---------|--------|
| 1 | `backend/bootstrap/app.php` | CORS dynamique via `config('cors.allowed_origins')` dans le handler d'exceptions |
| 2 | `backend/app/Http/Middleware/CorsMiddleware.php` | CORS dynamique via `config('cors.allowed_origins')` dans le middleware |
| 3 | `backend/app/Jobs/ProcessAudioRecording.php` | Queue nommee `audio` + cleanup temp garanti (try/finally) |
| 4 | `backend/app/Jobs/AnalyzeImportFileJob.php` | Queue nommee `import` |
| 5 | `backend/app/Jobs/ProcessImportSessionJob.php` | Queue nommee `import` |
| 6 | `backend/app/Jobs/ProcessImportBatchJob.php` | Queue nommee `import` |
| 7 | `deploy/worker-server/docker-compose.yml` | 3 audio workers + 1 import worker + volume cache HF + grace period 350s |
| 8 | `deploy/app-server/docker-compose.yml` | Volume cache HuggingFace sur backend |
| 9 | `deploy/.env.production.example` | Section IA complete (WHISPER, MISTRAL, PYANNOTE) |

---

## Detail des corrections

### 1. CORS dans `bootstrap/app.php` — BLOQUANT

**Probleme** : `Access-Control-Allow-Origin: http://localhost:5173` hardcode dans le handler d'exceptions. En production, toutes les requetes CORS en erreur echouent.

**Avant** :
```php
$exceptions->respond(function (\Symfony\Component\HttpFoundation\Response $response) {
    $response->headers->set('Access-Control-Allow-Origin', 'http://localhost:5173');
    $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
    $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
    return $response;
});
```

**Apres** :
```php
$exceptions->respond(function (\Symfony\Component\HttpFoundation\Response $response, \Throwable $exception, \Illuminate\Http\Request $request) {
    $allowedOrigins = config('cors.allowed_origins', ['http://localhost:5173']);
    $origin = $request->headers->get('Origin');
    if (in_array($origin, $allowedOrigins, true)) {
        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        $response->headers->set('Access-Control-Allow-Credentials', 'true');
    }
    return $response;
});
```

**Changements cles** :
- Utilisation de `config()` au lieu de `env()` (compatible avec `config:cache` en production)
- Validation de l'origin avant d'ajouter les headers (securite)
- Ajout de `Access-Control-Allow-Credentials: true` (requis pour Sanctum)

> **Important** : `env()` retourne `null` apres `php artisan config:cache`. Utiliser `config()` est obligatoire dans tout code runtime (middleware, controllers, jobs). `env()` ne doit etre utilise que dans les fichiers `config/*.php`.

---

### 2. CORS dans `CorsMiddleware.php` — BLOQUANT

**Probleme** : Origins en dur (`localhost:5173`, `ton-domaine.fr`).

**Avant** :
```php
$allowedOrigins = [
    'http://localhost:5173',
    'https://ton-domaine.fr',
    'https://app.ton-domaine.fr',
];
```

**Apres** :
```php
$allowedOrigins = config('cors.allowed_origins', ['http://localhost:5173']);
```

**Source de verite** : `config/cors.php` qui lit `CORS_ALLOWED_ORIGINS` depuis l'env :
```php
// config/cors.php
'allowed_origins' => array_filter(
    explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:5173,http://127.0.0.1:5173'))
),
```

---

### 3-6. Queues nommees — PERFORMANCE

**Probleme** : Tous les jobs partagent la queue `default`. Un import CSV de 5000 lignes bloque les transcriptions audio pendant des minutes.

**Correction** :

| Job | Queue |
|-----|-------|
| `ProcessAudioRecording` | `audio` |
| `AnalyzeImportFileJob` | `import` |
| `ProcessImportSessionJob` | `import` |
| `ProcessImportBatchJob` | `import` |

Ajout d'une propriete `public $queue` dans chaque job :

```php
// ProcessAudioRecording.php
public $queue = 'audio';

// AnalyzeImportFileJob.php, ProcessImportSessionJob.php, ProcessImportBatchJob.php
public $queue = 'import';
```

Les workers consomment leur queue dediee + `default` en fallback :
- Audio workers : `--queue=audio,default`
- Import worker : `--queue=import,default`

**Correction supplementaire (ProcessAudioRecording)** : Le cleanup des fichiers temp est desormais garanti via `try/finally` au lieu d'un simple `@unlink` apres transcription. Evite l'accumulation de fichiers orphelins en cas d'echec.

---

### 7. Worker server : multi-workers + cache HuggingFace — PERFORMANCE

**Probleme** : 1 seul worker, 4 GB RAM. Insuffisant pour 80 users. Les modeles Whisper et Pyannote se re-telechargeaient a chaque restart.

**Avant** : 1 container `queue-worker` unique

**Apres** : 4 containers specialises

```yaml
services:
  audio-worker-1:    # queue=audio,default  | timeout=300s
  audio-worker-2:    # queue=audio,default  | timeout=300s
  audio-worker-3:    # queue=audio,default  | timeout=300s
  import-worker:     # queue=import,default | timeout=600s
  scheduler:         # cron (inchange)
```

**Volumes ajoutes** :
- `hf_cache:/var/www/.cache` sur les 3 audio workers (cache modeles HuggingFace/Whisper/Pyannote)

**Grace period** : `stop_grace_period: 350s` (300s timeout + 50s buffer) pour eviter le SIGKILL sur un job en cours de transcription.

**Dimensionnement serveur recommande** : **DEV1-M -> GP1-XS (4 vCPU, 16 GB RAM)**

---

### 8. App server : cache HuggingFace — STT/LLM

**Probleme** : Le service backend sur l'app-server utilise aussi Pyannote (diarisation en synchrone). Sans cache, les modeles (~1-3 GB) se re-telechargeaient a chaque restart.

**Ajout** :
```yaml
# deploy/app-server/docker-compose.yml
backend:
  volumes:
    - hf_cache:/var/www/.cache   # <-- ajoute

volumes:
  hf_cache:                       # <-- ajoute
```

---

### 9. Variables d'environnement IA — `.env.production.example`

**Probleme** : Variables `WHISPER_MODEL`, `MISTRAL_STT_MODEL`, `MISTRAL_LLM_MODEL`, `PYANNOTE_CHECK_ON_BOOT`, `PYANNOTE_MODEL` manquantes.

**Section IA complete** :
```env
# === APIs IA ===
OPENAI_API_KEY=sk-proj-xxx
HUGGINGFACE_TOKEN=hf_xxx

# Transcription (STT) — Mode : openai, mistral, ou local
TRANSCRIPTION_MODE=openai
WHISPER_MODEL=base
MISTRAL_API_KEY=xxx
MISTRAL_USE_FOR_STT=false
MISTRAL_STT_MODEL=voxtral-mini-latest

# LLM (extraction de donnees, resumes de RDV)
MISTRAL_USE_FOR_LLM=true
MISTRAL_LLM_MODEL=mistral-small-latest
MISTRAL_FALLBACK=true

# Diarisation (Pyannote — separation courtier/client)
PYANNOTE_CHECK_ON_BOOT=true
PYANNOTE_MODEL=pyannote/speaker-diarization-3.1
```

---

## Corrections issues de la code review

### A. `env()` remplace par `config()` — CRITIQUE

Apres la review, les appels `env('CORS_ALLOWED_ORIGINS')` dans `bootstrap/app.php` et `CorsMiddleware.php` ont ete remplaces par `config('cors.allowed_origins')`.

**Raison** : `env()` retourne `null` apres `php artisan config:cache` (obligatoire en production). La source de verite est `config/cors.php` qui lit `env()` au bon endroit.

### B. Cleanup temp garanti (try/finally)

`ProcessAudioRecording.php` : le `@unlink()` du fichier temporaire est maintenant dans un bloc `finally` pour garantir le nettoyage meme en cas d'exception pendant la transcription.

### C. Grace period augmentee a 350s

`worker-server/docker-compose.yml` : `stop_grace_period` passe de 310s a 350s (300s timeout job + 50s buffer) pour eviter de tuer un worker en pleine transcription lors d'un redemarrage.

---

## Impact sur le dimensionnement infra

| Composant | Ancien plan | Nouveau plan (80 users) |
|-----------|-------------|-------------------------|
| Worker server | DEV1-M (3 vCPU, 4 GB, 40 GB) — 10 EUR | **GP1-XS (4 vCPU, 16 GB, 150 GB) — ~30 EUR** |
| Queue workers | 1 (shared) | **3 audio + 1 import** |
| Perf 10 uploads simultanes | ~33 min | **~8-10 min** |
| Budget worker | 10 EUR/mois | **~30 EUR/mois** |
| **Budget total infra** | **~86 EUR/mois** | **~106 EUR/mois** |

---

## Points d'attention pour le deploiement

1. **Config cache obligatoire** : Executer `php artisan config:cache` apres chaque deploiement. Tous les appels CORS utilisent `config()` qui depend du cache.

2. **Premier demarrage des workers** : Les modeles Pyannote (~1.5 GB) se telechargeront au premier job audio. Prevoir 3-5 min de latence initiale. Les restarts suivants utiliseront le volume `hf_cache`.

3. **Race condition possible** : Si les 3 audio workers demarrent simultanement sans cache, ils peuvent tenter de telecharger le modele en parallele. Option : demarrer `audio-worker-1` seul, attendre le premier job, puis demarrer les 2 autres.

4. **Monitoring des queues** : Surveiller `redis:audio` et `redis:import` independamment. Si `audio` depasse 10 jobs en attente regulierement, ajouter un worker.

---

## Verification post-deploiement

### CORS
```bash
curl -H "Origin: https://app.mondomaine.fr" -I https://api.mondomaine.fr/api/health/audio
# Verifier : Access-Control-Allow-Origin: https://app.mondomaine.fr
```

### Cache modeles HuggingFace
```bash
docker compose restart audio-worker-1
docker compose exec audio-worker-1 ls /var/www/.cache/huggingface/
# Verifier : fichiers presents (pas de re-telechargement)
```

### Queues separees
```bash
docker compose exec audio-worker-1 php artisan queue:monitor redis:audio,redis:import,redis:default
```

### Test de charge
Uploader 5 fichiers audio simultanement et verifier que 3 se traitent en parallele (1 par audio-worker).

### Config cache
```bash
docker compose exec backend php artisan config:cache
docker compose exec backend php artisan config:show cors
# Verifier : allowed_origins contient le domaine prod
```
