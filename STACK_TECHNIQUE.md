# Stack Technique - CRM-AI Courtier

> Document de référence pour le dimensionnement et le déploiement en production.
> Dernière mise à jour : 30 janvier 2026

---

## 1. Vue d'ensemble du projet

### 1.1 Objectifs fonctionnels

CRM-AI est un CRM spécialisé pour les courtiers en assurance et gestion de patrimoine, avec intelligence artificielle intégrée pour :

- **Automatisation de la saisie client** via transcription audio des rendez-vous
- **Extraction automatique des données** (état civil, revenus, passifs, actifs, patrimoine immobilier)
- **Génération de documents réglementaires** (DER, fiches conseil, lettres de mission)
- **Suivi de conformité RGPD** avec gestion documentaire
- **Import de données** depuis fichiers (CSV, Excel) ou bases de données externes

### 1.2 Cas d'usage principaux

| Cas d'usage | Description | Services impliqués |
|-------------|-------------|-------------------|
| Enregistrement RDV | Le courtier enregistre un entretien client (jusqu'à 2h) | Audio recorder, Whisper, Pyannote |
| Extraction IA | Les données sont extraites automatiquement de la transcription | GPT-4o-mini, 10 extracteurs spécialisés |
| Validation des données | Le courtier valide/modifie les changements proposés par l'IA | PendingChanges, MergeService |
| Génération documents | Création automatique DER, fiches conseil, etc. | Gotenberg, PHPWord, DomPDF |
| Import de données | Migration depuis CRM tiers ou fichiers clients | ImportOrchestrationService, RGPD |
| Compliance tracking | Suivi des documents obligatoires (CNI, avis imposition, RIB) | ComplianceService |

### 1.3 Contraintes techniques et métier

| Contrainte | Description | Impact |
|------------|-------------|--------|
| **RGPD** | Données personnelles sensibles (santé, patrimoine, fiscalité) | Chiffrement, audit trail, consentement explicite |
| **Multi-tenant** | Isolation stricte entre cabinets de courtage | Scope global team_id sur toutes les requêtes |
| **Audio long** | Enregistrements jusqu'à 2h en chunks de 30s | Chunked upload, reconstruction côté serveur |
| **Temps réel** | Feedback utilisateur pendant traitement IA | Queues Redis, statuts polling |
| **Disponibilité** | Service métier critique pour le courtier | Objectif 99.5% uptime |

---

## 2. Stack applicative

### 2.1 Frontend

| Composant | Technologie | Version | Rôle |
|-----------|-------------|---------|------|
| Framework | React | 19.1.1 | UI rendering |
| Langage | TypeScript | 5.9.3 | Type safety |
| Build tool | Vite | 7.1.7 | Dev server + bundling |
| Routing | React Router DOM | 7.9.4 | SPA navigation |
| Styling | Tailwind CSS | 4.1.16 | Utility-first CSS |
| Design System | Vuexy | Custom | Composants UI cohérents |
| HTTP Client | Axios | 1.12.2 | Appels API REST |
| Audio | RecordRTC | 5.6.2 | Enregistrement WebRTC |
| Icons | Lucide React | 0.555.0 | Iconographie |

**Structure du code frontend :**
```
frontend/src/
├── pages/           # 15 pages/routes
├── components/      # 30+ composants réutilisables
├── contexts/        # AuthContext (Sanctum)
├── api/             # apiClient.ts configuré
├── types/           # Interfaces TypeScript
└── styles/          # CSS global
```

### 2.2 Backend

| Composant | Technologie | Version | Rôle |
|-----------|-------------|---------|------|
| Framework | Laravel | 12.0 | API REST |
| Langage | PHP | 8.3+ | Backend logic |
| Auth API | Laravel Sanctum | 4.2 | Token-based auth |
| Auth UI | Laravel Fortify | 1.31 | Auth scaffolding |
| Performance | Laravel Octane | 2.13 | Swoole async server |
| Permissions | Spatie Permission | 6.23 | RBAC |
| PDF | DomPDF + Gotenberg | 3.1 | Génération PDF |
| Excel | PhpSpreadsheet | 5.3 | Import/export Excel |
| Word | PhpWord | 1.3 | Génération DOCX |
| Storage | Flysystem S3 | 3.0 | Abstraction stockage |

**Architecture backend :**
```
backend/app/
├── Models/              # 37 modèles Eloquent
├── Services/            # 35+ services métier
│   ├── Ai/              # Services IA (extracteurs, normalisation)
│   └── Import/          # Pipeline import (8 services)
├── Jobs/                # 4 jobs asynchrones
├── Http/Controllers/    # 22 contrôleurs REST
├── Observers/           # Event listeners
├── Scopes/              # TeamScope (multi-tenant)
└── Traits/              # HasTeams
```

### 2.3 APIs internes

| Endpoint | Méthode | Description | Rate limit |
|----------|---------|-------------|------------|
| `/api/clients` | CRUD | Gestion fiches clients | Standard |
| `/api/audio/upload` | POST | Upload audio complet | throttle:audio-upload |
| `/api/recordings/chunk` | POST | Upload chunk 30s | throttle:audio-chunk |
| `/api/recordings/{id}/finalize` | POST | Finalise enregistrement | throttle:audio-finalize |
| `/api/pending-changes` | GET/POST | Validation changements IA | Standard |
| `/api/import/sessions` | CRUD | Sessions d'import | Standard |
| `/api/clients/{id}/compliance/*` | CRUD | Documents réglementaires | Standard |

**Total : 100+ endpoints REST**

### 2.4 Services tiers

| Service | Fournisseur | Usage | Criticité |
|---------|-------------|-------|-----------|
| **Transcription** | OpenAI Whisper API | Fallback si local indisponible | Haute |
| **Extraction IA** | OpenAI GPT-4o-mini | Extraction données structurées | Critique |
| **Diarisation** | Pyannote (HuggingFace) | Séparation speakers | Moyenne |

---

## 3. Stack data

### 3.1 Bases de données

| Base | Moteur | Version | Usage | Volumétrie estimée |
|------|--------|---------|-------|-------------------|
| **Principale** | MariaDB | 11 | Données applicatives | 10-50 GB (500 clients/cabinet) |
| **Cache** | Redis | 7 | Cache + Queues | 1-2 GB |

**Schéma principal (73 migrations) :**

| Groupe | Tables | Description |
|--------|--------|-------------|
| Clients | `clients`, `conjoints`, `enfants`, `entreprises` | Données état civil |
| Patrimoine | `client_revenus`, `client_passifs`, `client_actifs_financiers`, `client_biens_immobiliers`, `client_autres_epargnes`, `client_charges` | Données financières |
| Audio | `audio_records`, `recording_sessions`, `diarization_logs` | Enregistrements et transcriptions |
| Documents | `document_templates`, `generated_documents`, `client_compliance_documents` | Génération et compliance |
| Import | `import_sessions`, `import_rows`, `import_mappings`, `import_audit_logs` | Pipeline import |
| Auth | `users`, `teams`, `team_user`, `personal_access_tokens` | Multi-tenant |

**Relations clés :**
- `Client` → belongsTo `Team` (isolation multi-tenant via TeamScope)
- `Client` → hasMany `ClientRevenu`, `ClientPassif`, `ClientActifFinancier`, etc.
- `AudioRecord` → belongsTo `Client`
- `User` → belongsToMany `Team`

### 3.2 Stockage fichiers

| Type | Stockage | Format | Rétention |
|------|----------|--------|-----------|
| Audio brut | S3 | .webm, .mp3, .wav | Configurable (défaut: illimité) |
| Documents générés | S3 | .pdf, .docx | Illimité |
| Documents compliance | S3 | .pdf, .jpg, .png | Durée légale |
| Fichiers import | S3 temporaire | .csv, .xlsx | `retention_until` (RGPD) |

**Configuration S3 :**
```
Production:  AWS S3 (eu-west-3)
Développement: MinIO local (compatible S3)
Bucket: crm-ai-bucket
```

### 3.3 Flux de données

```
┌─────────────────────────────────────────────────────────────────────┐
│                        FLUX PRINCIPAL                                │
│                                                                      │
│  [Audio Upload] → [Queue Redis] → [Job ProcessAudioRecording]        │
│                                          │                           │
│                    ┌─────────────────────┼─────────────────────┐     │
│                    │                     │                     │     │
│                    ▼                     ▼                     ▼     │
│            [Transcription]       [Diarisation]         [Extraction]  │
│            Whisper local/API     Pyannote              GPT-4o-mini   │
│                    │                     │                     │     │
│                    └─────────────────────┼─────────────────────┘     │
│                                          │                           │
│                                          ▼                           │
│                              [AiDataNormalizer]                      │
│                                          │                           │
│                                          ▼                           │
│                              [PendingChanges]                        │
│                                          │                           │
│                                          ▼                           │
│                         [Validation utilisateur]                     │
│                                          │                           │
│                                          ▼                           │
│                              [MergeService → DB]                     │
└─────────────────────────────────────────────────────────────────────┘
```

---

## 4. Stack IA / Automatisation

### 4.1 Modèles utilisés

| Service | Modèle | Fournisseur | Mode | Coût estimé |
|---------|--------|-------------|------|-------------|
| **Transcription** | Whisper base | Local (Python) | Consommation | 0€ (CPU) |
| **Transcription fallback** | whisper-1 | OpenAI API | Consommation | ~$0.006/min |
| **Extraction** | gpt-4o-mini | OpenAI API | Consommation | ~$0.15/1M input tokens |
| **Diarisation** | speaker-diarization-3.1 | Pyannote/HuggingFace | Consommation | 0€ (licence requise) |

### 4.2 Orchestration IA

**Architecture modulaire :**
```
AnalysisService (Orchestrateur)
├── RouterService              → Détecte sections pertinentes
├── 10 Extracteurs spécialisés
│   ├── ClientExtractor        → État civil, famille
│   ├── ClientRevenusExtractor → Sources de revenus
│   ├── ClientPassifsExtractor → Crédits, dettes
│   ├── ClientActifsFinanciersExtractor
│   ├── ClientBiensImmobiliersExtractor
│   ├── ClientAutresEpargnesExtractor
│   ├── ConjointExtractor
│   ├── PrevoyanceExtractor    → BAE prévoyance
│   ├── RetraiteExtractor      → BAE retraite
│   └── EpargneExtractor       → BAE épargne
├── AiDataNormalizer           → Normalisation données
└── ExtractionGuardrailsService → Validation/guardrails
```

**Paramètres IA :**
```php
Temperature: 0.1          // Déterminisme
Model: gpt-4o-mini        // Coût optimisé
Response format: JSON     // Structured output
```

### 4.3 Jobs asynchrones

| Job | Trigger | Timeout | Retries |
|-----|---------|---------|---------|
| `ProcessAudioRecording` | Upload finalisé | 300s | 3 |
| `AnalyzeImportFileJob` | Import fichier | 120s | 3 |
| `ProcessImportBatchJob` | Batch import | 300s | 3 |
| `ProcessImportSessionJob` | Session import | 600s | 3 |

---

## 5. Infrastructure cible (Production)

### 5.1 Architecture recommandée

```
                        ┌─────────────────┐
                        │   CloudFlare    │
                        │   (CDN + WAF)   │
                        └────────┬────────┘
                                 │
                        ┌────────▼────────┐
                        │  Load Balancer  │
                        │    (Nginx)      │
                        └────────┬────────┘
                                 │
              ┌──────────────────┼──────────────────┐
              │                  │                  │
     ┌────────▼────────┐ ┌──────▼──────┐ ┌────────▼────────┐
     │   Frontend      │ │   Backend   │ │  Queue Worker   │
     │   (Static/CDN)  │ │  (Laravel)  │ │   (Laravel)     │
     │   React build   │ │  Octane     │ │   redis queue   │
     └─────────────────┘ └──────┬──────┘ └────────┬────────┘
                                │                  │
              ┌─────────────────┼──────────────────┤
              │                 │                  │
     ┌────────▼────────┐ ┌─────▼──────┐ ┌────────▼────────┐
     │    MariaDB      │ │   Redis    │ │      S3         │
     │   (Primary)     │ │  (Cache)   │ │   (Storage)     │
     │   + Replica     │ └────────────┘ └─────────────────┘
     └─────────────────┘
```

### 5.2 Options d'hébergement

| Option | Description | Avantages | Inconvénients |
|--------|-------------|-----------|---------------|
| **AWS** | EC2 + RDS + S3 + ElastiCache | Scalabilité, services managés | Coût, complexité |
| **Scaleway** | Instances + Managed DB + Object Storage | Prix FR, RGPD, simplicité | Moins de services |
| **OVH** | VPS + CloudDB + Object Storage | Prix, datacenter FR | Moins automatisé |
| **Render/Railway** | PaaS tout-en-un | Simplicité déploiement | Coût à l'échelle |

**Recommandation :** Scaleway ou OVH pour conformité RGPD (datacenter France) + coût maîtrisé.

### 5.3 Dimensionnement estimé

**Hypothèses :**
- 50 cabinets (teams)
- 500 clients/cabinet en moyenne
- 20 enregistrements audio/jour/cabinet
- 5 Go stockage/cabinet/mois

| Ressource | Dev | Staging | Production |
|-----------|-----|---------|------------|
| **CPU Backend** | 2 vCPU | 2 vCPU | 4-8 vCPU |
| **RAM Backend** | 4 GB | 4 GB | 8-16 GB |
| **CPU Worker** | 2 vCPU | 2 vCPU | 4 vCPU |
| **RAM Worker** | 4 GB | 4 GB | 8 GB |
| **MariaDB** | 2 GB RAM | 4 GB RAM | 8 GB RAM |
| **Redis** | 512 MB | 1 GB | 2-4 GB |
| **S3 Storage** | 10 GB | 50 GB | 500 GB+ |

### 5.4 Conteneurisation

**Docker Compose actuel (développement) :**
```yaml
services:
  backend:      # PHP 8.3 Apache + Laravel
  frontend:     # Vite dev server
  db:           # MariaDB 11
  redis:        # Redis 7 Alpine
  queue-worker: # Laravel queue:work
  minio:        # S3 local
  gotenberg:    # PDF conversion
  phpmyadmin:   # DB admin
  mailhog:      # SMTP test
```

**Production recommandée :**
- Docker Compose pour petite/moyenne charge
- Kubernetes (K8s) pour haute disponibilité

### 5.5 CI/CD

**Pipeline recommandé :**
```
┌─────────┐    ┌─────────┐    ┌─────────┐    ┌─────────┐
│  Push   │ →  │  Test   │ →  │  Build  │ →  │ Deploy  │
│  Git    │    │ PHPUnit │    │ Docker  │    │ Staging │
└─────────┘    │ ESLint  │    │ Images  │    │ / Prod  │
               └─────────┘    └─────────┘    └─────────┘
```

**Outils suggérés :**
- GitHub Actions ou GitLab CI
- Docker Registry (GHCR, ECR, Scaleway Registry)
- Deployment: Docker Compose ou Kubernetes

### 5.6 Environnements

| Environnement | URL | Base de données | Objectif |
|---------------|-----|-----------------|----------|
| **Local** | localhost:5173/8000 | MariaDB Docker | Développement |
| **Staging** | staging.app.com | DB dédiée | Tests intégration |
| **Production** | app.com | DB production | Clients réels |

---

## 6. Sécurité et conformité

### 6.1 Gestion des secrets

| Secret | Stockage dev | Stockage prod |
|--------|--------------|---------------|
| `APP_KEY` | .env | Variable env serveur |
| `DB_PASSWORD` | .env | Secrets manager |
| `OPENAI_API_KEY` | .env | Secrets manager |
| `HUGGINGFACE_TOKEN` | .env | Secrets manager |
| `AWS_SECRET_ACCESS_KEY` | .env | IAM Role (si AWS) |

**Recommandation prod :** AWS Secrets Manager, HashiCorp Vault, ou variables d'environnement système.

### 6.2 Authentification / Autorisation

| Couche | Technologie | Configuration |
|--------|-------------|---------------|
| **API Auth** | Laravel Sanctum | Token Bearer, stateless |
| **RBAC** | Spatie Permission | Rôles: owner, admin, editor, viewer |
| **Multi-tenant** | TeamScope | Isolation automatique par team_id |

**Flow d'authentification :**
```
1. POST /api/login → Token Bearer
2. Header: Authorization: Bearer {token}
3. Middleware: auth:sanctum
4. Scope: TeamScope appliqué automatiquement
```

### 6.3 RGPD et données sensibles

| Mesure | Implémentation | Statut |
|--------|----------------|--------|
| **Consentement** | `rgpd_consent_given`, `consent_timestamp` | Actif |
| **Base légale** | `legal_basis` (consent, contract, legitimate_interest) | Actif |
| **Audit trail** | `import_audit_logs`, `audit_logs` | Actif |
| **Rétention** | `retention_until` sur imports temporaires | Actif |
| **Droit à l'oubli** | À implémenter (soft delete + purge) | Planifié |
| **Portabilité** | Export JSON/CSV (à implémenter) | Planifié |

**Données sensibles identifiées :**
- État civil, adresse, téléphone, email
- Revenus, patrimoine, dettes
- Informations santé (besoins santé)
- Documents d'identité (CNI, passeport)

### 6.4 Sauvegardes

| Composant | Stratégie recommandée | Rétention |
|-----------|----------------------|-----------|
| **MariaDB** | Backup quotidien + WAL | 30 jours |
| **S3** | Versioning activé | 90 jours |
| **Redis** | AOF persistence | N/A (cache) |

---

## 7. Scalabilité et performances

### 7.1 Points de charge identifiés

| Point | Description | Charge estimée | Mitigation |
|-------|-------------|----------------|------------|
| **Upload audio** | Chunks 30s × 240 pour 2h | 100-500 MB/session | S3 direct upload |
| **Transcription** | Whisper CPU intensive | 1-5 min/audio | Workers dédiés |
| **Extraction IA** | Appels OpenAI | 10-50 calls/audio | Rate limiting, batching |
| **Génération PDF** | Gotenberg | 5-30s/document | Cache templates |

### 7.2 Stratégies de montée en charge

| Stratégie | Seuil | Action |
|-----------|-------|--------|
| **Horizontal** | CPU > 70% | Ajout workers queue |
| **Vertical** | RAM > 80% | Upgrade instance |
| **Cache** | Requêtes répétées | Redis cache (TTL 1h) |
| **CDN** | Assets statiques | CloudFlare/Fastly |

**Laravel Octane :**
```php
OCTANE_SERVER=swoole
OCTANE_WORKERS=auto        // CPU cores
OCTANE_MAX_REQUESTS=500    // Recycle process
```

### 7.3 Observabilité

| Aspect | Outil recommandé | Alternative |
|--------|------------------|-------------|
| **Logs** | Laravel Log + Elasticsearch | Papertrail, Logtail |
| **Métriques** | Prometheus + Grafana | Datadog, New Relic |
| **APM** | Laravel Telescope (dev) | Sentry, Bugsnag |
| **Uptime** | UptimeRobot | Pingdom |
| **Alertes** | PagerDuty/Slack | Email |

**Logs applicatifs :**
```
storage/logs/laravel.log
- Niveau: DEBUG (dev), WARNING (prod)
- Format: JSON structuré recommandé
- Rétention: 14 jours
```

---

## 8. Variables d'environnement clés

```env
# === APPLICATION ===
APP_NAME=CRM-AI
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:xxxxx

# === DATABASE ===
DB_CONNECTION=mysql
DB_HOST=db.example.com
DB_PORT=3306
DB_DATABASE=crm_ai
DB_USERNAME=crm_user
DB_PASSWORD=<secret>

# === REDIS ===
REDIS_HOST=redis.example.com
REDIS_PORT=6379
REDIS_PASSWORD=<secret>
QUEUE_CONNECTION=redis
CACHE_STORE=redis

# === STORAGE S3 ===
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=<key>
AWS_SECRET_ACCESS_KEY=<secret>
AWS_DEFAULT_REGION=eu-west-3
AWS_BUCKET=crm-ai-production
AWS_ENDPOINT=              # Vide pour AWS, URL pour S3-compatible
AWS_USE_PATH_STYLE_ENDPOINT=false

# === SERVICES IA ===
OPENAI_API_KEY=sk-proj-xxxxx
OPENAI_PROJECT_ID=proj_xxxxx
TRANSCRIPTION_MODE=local   # local ou openai
WHISPER_MODEL=base         # tiny, base, small, medium, large
HUGGINGFACE_TOKEN=hf_xxxxx

# === FRONTEND ===
FRONTEND_URL=https://app.example.com
SANCTUM_STATEFUL_DOMAINS=app.example.com

# === OCTANE ===
OCTANE_SERVER=swoole
```

---

## 9. Annexes

### 9.1 Dépendances Python (IA)

```
openai-whisper    # Transcription locale
torch             # PyTorch framework
pyannote.audio    # Diarisation speakers
```

**Installation :**
```bash
pip install openai-whisper torch pyannote.audio
```

### 9.2 Commandes utiles

```bash
# Backend
php artisan migrate
php artisan db:seed
php artisan queue:work redis --tries=3 --timeout=300
php artisan octane:start --server=swoole

# Frontend
npm run build
npm run preview

# Docker
docker compose up -d
docker compose logs -f backend
docker compose exec backend php artisan tinker
```

### 9.3 Endpoints santé

| Endpoint | Description |
|----------|-------------|
| `GET /api/health/audio` | Vérifie Whisper disponible |
| `GET /api/health/pyannote` | Vérifie Pyannote disponible |

---

## 10. Hypothèses et décisions ouvertes

| Sujet | Hypothèse actuelle | Décision requise |
|-------|-------------------|------------------|
| **Hébergement** | Scaleway/OVH (datacenter FR) | Choix définitif selon budget |
| **Whisper** | Mode local prioritaire | GPU ou API-only selon coût |
| **Pyannote** | Optionnel (dégradation gracieuse) | Inclure ou retirer |
| **Sauvegardes** | Backup quotidien 30j | Fréquence et rétention exactes |
| **Multi-région** | Non prévu | À évaluer si expansion internationale |
| **Kubernetes** | Non requis initialement | À évaluer si >100 cabinets |

---

*Document généré pour transmission à un architecte technique ou LLM pour définition de l'architecture cible et chiffrage infrastructure.*
