# Architecture Globale du CRM IA Courtier

## Vue d'ensemble

**Nom du projet :** CRM IA Courtier (courtier-whisper)
**Type :** Application web full-stack avec traitement IA pour courtiers en assurance
**Architecture :** Monorepo Docker avec backend Laravel et frontend React

## Stack Technologique Complète

### Backend
- **Framework :** Laravel 12 (PHP 8.3)
- **Serveur web :** Apache 2.4 (via Docker)
- **Base de données :** MariaDB 11
- **Cache & Queues :** Redis 7 (Alpine)
- **Performance :** Laravel Octane avec Swoole
- **Authentification :** Laravel Sanctum (token-based API)
- **Permissions :** Spatie Laravel Permission (RBAC)
- **ORM :** Eloquent avec TeamScope (multi-tenancy)

### Frontend
- **Framework :** React 19.1.1 avec TypeScript 5.9.3
- **Build tool :** Vite 7.1.7
- **Routing :** React Router DOM 7.9.4
- **Styling :** Tailwind CSS 4.1.16 (thème Vuexy)
- **Icônes :** Lucide React 0.555
- **HTTP Client :** Axios 1.12.2
- **Notifications :** React Toastify 11.0.5
- **Enregistrement audio :** RecordRTC 5.6.2

### Intelligence Artificielle
- **Transcription :** OpenAI Whisper API (mode cloud)
- **Analyse NLP :** OpenAI GPT-4o-mini
- **Diarisation :** Pyannote.audio 3.1 (HuggingFace)
- **Format audio :** WebM (frontend) → WAV (backend processing)
- **Traitement :** Python 3 (scripts embarqués dans container Laravel)

### Infrastructure & Orchestration
- **Conteneurisation :** Docker + Docker Compose v2
- **Services :**
  - `backend` (Laravel + Apache + Python)
  - `frontend` (Vite dev server)
  - `db` (MariaDB 11)
  - `redis` (cache + queues)
  - `queue-worker` (Laravel queue worker)
  - `mailhog` (test SMTP)
  - `gotenberg` (conversion PDF)
  - `phpmyadmin` (admin DB)
- **Volumes persistants :** `mariadb_data`, `redis_data`

### Outils de conversion documents
- **PDF :** DomPDF (Laravel) + Gotenberg 8
- **Word :** PHPWord 1.3
- **Templates :** Variables dynamiques mappées depuis la BDD

## Architecture Applicative

### Modèle MVC Backend (Laravel)

```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/          # 11+ contrôleurs REST
│   │   ├── Requests/             # Validation des requêtes
│   │   ├── Resources/            # Transformation JSON
│   │   └── Middleware/           # Auth, CORS, throttle
│   ├── Models/                   # 24+ modèles Eloquent
│   ├── Services/                 # 30+ services métier
│   │   ├── Ai/                   # Pipeline IA
│   │   │   ├── AnalysisService   # Orchestrateur
│   │   │   ├── RouterService     # Détection sections
│   │   │   └── Extractors/       # 10+ extracteurs spécialisés
│   │   ├── AudioService          # Upload + traitement audio
│   │   ├── TranscriptionService  # Whisper API
│   │   ├── DiarizationService    # Pyannote
│   │   ├── DocumentGeneratorService
│   │   └── *SyncService          # Sync BDD après extraction
│   ├── Scopes/
│   │   └── TeamScope             # Multi-tenancy automatique
│   └── Policies/                 # Autorisations (Policy Pattern)
├── database/
│   └── migrations/               # 50+ migrations
├── routes/
│   └── api.php                   # 60+ routes RESTful
└── scripts/                      # Scripts Python
    ├── whisper_transcribe.py
    ├── diarize_audio.py
    └── requirements.txt
```

### Architecture Frontend (React)

```
frontend/
├── src/
│   ├── components/               # Composants réutilisables
│   │   ├── LongRecorder.tsx      # Enregistrement long (2h max)
│   │   ├── CollapsibleSection.tsx
│   │   ├── Modal/
│   │   └── Layout/
│   ├── pages/                    # Pages principales
│   │   ├── LoginPage.tsx
│   │   ├── ClientsPage.tsx       # Liste clients + filtres
│   │   ├── ClientDetailPage.tsx  # Fiche client complète
│   │   ├── ClientEditPage.tsx    # Édition client + relations
│   │   └── CreateClientPage.tsx  # Création + enregistrement
│   ├── api/
│   │   └── axios.ts              # Config Axios + interceptors
│   ├── types/                    # Interfaces TypeScript
│   └── main.tsx                  # Point d'entrée
└── public/
```

## Flux de Données Principal

### 1. Enregistrement Audio → Extraction IA → Mise à Jour Client

```mermaid
flowchart TD
    A[Frontend: Enregistrement audio] --> B[RecordRTC capture audio]
    B --> C{Durée < 10min?}
    C -->|Oui| D[POST /api/audio/upload]
    C -->|Non| E[Chunking 10min via /api/recordings/chunk]
    E --> F[POST /api/recordings/{sessionId}/finalize]
    D --> G[AudioService::uploadAndProcess]
    F --> G
    G --> H[Job ProcessAudioRecording dispatché sur Redis Queue]
    H --> I[TranscriptionService: Whisper API]
    I --> J{Diarisation activée?}
    J -->|Oui| K[DiarizationService: Pyannote]
    J -->|Non| L[RouterService: Détection sections GPT]
    K --> L
    L --> M[AnalysisService: Extraction modulaire]
    M --> N[10+ Extracteurs spécialisés GPT-4o-mini]
    N --> O[AiDataNormalizer: Validation + normalisation]
    O --> P[ClientSyncService + SyncServices]
    P --> Q[Update BDD: Client + relations]
    Q --> R[AudioRecord.status = 'done']
    R --> S[Frontend: Polling /api/audio/status/{id}]
    S --> T[Affichage données client mises à jour]
```

### 2. Édition Manuelle Client

```mermaid
flowchart LR
    A[ClientEditPage] --> B[Affichage sections collapsibles]
    B --> C[Modification données]
    C --> D[PUT /api/clients/{id}]
    D --> E[ClientController::update]
    E --> F[Validation + TeamScope]
    F --> G[Update Eloquent]
    G --> H[Response JSON]
    H --> I[Frontend: Toast success]
```

### 3. Génération Documents

```mermaid
flowchart TD
    A[Demande génération document] --> B[POST /api/clients/{id}/documents/generate]
    B --> C[DocumentGeneratorService]
    C --> D[Load template Word/HTML]
    D --> E[DirectTemplateMapper: Remplacement variables]
    E --> F{Format?}
    F -->|PDF| G[DomPDF ou Gotenberg]
    F -->|Word| H[PHPWord]
    G --> I[Stockage storage/app/documents]
    H --> I
    I --> J[GeneratedDocument enregistré en BDD]
    J --> K[Response avec document_id]
    K --> L[Frontend: Download via /api/documents/{id}/download]
```

## Architecture de Données

### Modèle Multi-Tenancy (Team-based)

- **Modèle :** Chaque `Client`, `AudioRecord`, `RecordingSession` appartient à une `Team`
- **Implémentation :** `TeamScope` appliqué globalement sur les modèles
- **Isolation :** Aucune fuite de données entre équipes
- **Users :** Relation `belongsToMany` avec `Team` (un user peut appartenir à plusieurs teams)
- **Current Team :** User a une `current_team_id` pour le contexte actuel

### Relations Eloquent Principales

#### Client (hub central)
```php
Client
├── belongsTo: Team (multi-tenancy)
├── belongsTo: User (créateur)
├── hasOne: Conjoint
├── hasMany: Enfant (1..n)
├── hasOne: SanteSouhait (besoins santé)
├── hasOne: BaePrevoyance (besoins prévoyance)
├── hasOne: BaeRetraite (besoins retraite)
├── hasOne: BaeEpargne (besoins épargne)
├── hasMany: ClientRevenu (sources revenus)
├── hasMany: ClientPassif (prêts, dettes)
├── hasMany: ClientActifFinancier (AV, PEA, etc.)
├── hasMany: ClientBienImmobilier (patrimoine immo)
├── hasMany: ClientAutreEpargne (or, crypto, etc.)
├── hasOne: QuestionnaireRisque (profil risque)
├── hasMany: GeneratedDocument (docs générés)
├── hasMany: AudioRecord (historique audio)
└── hasMany: RecordingSession (sessions enregistrement)
```

## Pipeline IA - Architecture Modulaire

### 1. RouterService (Détection des sections)

**Rôle :** Analyser la transcription et détecter les sections métier concernées
**Modèle :** GPT-4o-mini (temperature: 0.1)
**Input :** Transcription brute
**Output :** `["client", "conjoint", "prevoyance", "retraite", ...]`
**Garde-fou :** Détection forcée du conjoint si mots-clés ("ma femme", "mon mari")

### 2. AnalysisService (Orchestrateur)

**Rôle :** Coordonner l'extraction modulaire
**Pattern :** Strategy Pattern avec extracteurs spécialisés
**Process :**
1. Appeler RouterService
2. Pour chaque section détectée → appeler l'extracteur correspondant
3. Merger les données extraites (fusion intelligente)
4. Garde-fou : Nettoyer données client si correspondent au conjoint
5. Normaliser via AiDataNormalizer

### 3. Extracteurs Spécialisés (10+)

Chaque extracteur a son prompt dédié et extrait des champs spécifiques :

- **ClientExtractor :** Identité, coordonnées, situation familiale, profession
- **ConjointExtractor :** Données du conjoint uniquement
- **PrevoyanceExtractor :** Besoins prévoyance (ITT, décès, etc.)
- **RetraiteExtractor :** Besoins retraite (PER, TMI, etc.)
- **EpargneExtractor :** Besoins épargne (patrimoine, investissements)
- **ClientRevenusExtractor :** Sources de revenus (salaire, loyers, etc.)
- **ClientPassifsExtractor :** Prêts, emprunts
- **ClientActifsFinanciersExtractor :** Actifs financiers (AV, PEA, SCPI)
- **ClientBiensImmobiliersExtractor :** Biens immobiliers
- **ClientAutresEpargnesExtractor :** Épargnes alternatives (or, crypto)

### 4. AiDataNormalizer

**Rôle :** Validation et normalisation post-extraction
**Transformations :**
- Dates : Conversion en format ISO (YYYY-MM-DD)
- Téléphones : Normalisation format français
- Besoins : Mapping mots-clés → valeurs normalisées
- Booléens : Détection négation ("je ne suis PAS fumeur" → false)
- Noms de villes : Conservation du nom complet

### 5. SyncServices (Persistance)

Services dédiés pour synchroniser chaque type de relation :
- **ClientSyncService :** Sync données client principal
- **ConjointSyncService :** Create/Update conjoint
- **EnfantSyncService :** Sync tableau enfants
- **ClientRevenusSyncService, ClientPassifsSyncService, etc.**

**Pattern :** AbstractSyncService (méthode `sync()` commune)

## Sécurité & Performance

### Authentification & Autorisation

- **Sanctum tokens :** Token API pour chaque user
- **Middleware :** `auth:sanctum` sur toutes les routes protégées
- **Policies :** Vérification ownership + team isolation
- **CORS :** Configuration stricte (localhost:5173 en dev)

### Rate Limiting (Throttling)

Routes critiques protégées :
- `throttle:audio-upload` → 10 requêtes/minute
- `throttle:audio-chunk` → 60 requêtes/minute
- `throttle:audio-finalize` → 10 requêtes/minute
- `throttle:speaker-correction` → 30 requêtes/minute
- `throttle:health-check` → 60 requêtes/minute

### Cache & Queues (Redis)

- **Cache :** Redis DB 1 (durée configurable)
- **Queues :** Redis DB 0 avec `queue:work` dédié
- **Jobs asynchrones :**
  - `ProcessAudioRecording` (transcription + extraction IA)
  - Timeout: 300s (5 minutes)
  - Retry: 3 fois

### Optimisations

- **Laravel Octane :** Serveur Swoole avec workers auto
- **Eloquent Eager Loading :** Relations chargées en avance (`with()`)
- **Indexes BDD :** Foreign keys indexées, `team_id` partout
- **Redis persistence :** AOF (Append-Only File) activé
- **OpCache PHP :** Activé via custom.ini

## Monitoring & Logs

### Health Checks

- `GET /api/health/audio` → Vérifie FFmpeg
- `GET /api/health/pyannote` → Vérifie Pyannote disponibilité
- `GET /api/diarization/stats` → Stats diarisation (précision, corrections)

### Audit & Traçabilité

- **AuditLog :** Enregistrement de toutes les actions sensibles
  - Client CRUD
  - Document générations
  - Exports
  - Corrections diarisation
- **DiarizationLog :** Métriques de qualité de la diarisation
  - Nombre de speakers détectés
  - Nombre de corrections utilisateur
  - Précision calculée

### Logs Laravel

- **Channel :** `stack` (single file en dev)
- **Level :** `debug` en dev, `error` en prod recommandé
- **Contexte IA :** Logs détaillés à chaque étape du pipeline

## Scalabilité Actuelle

### Points de scalabilité existants

✅ **Multi-tenancy :** Architecture team-based permet isolation naturelle
✅ **Queue workers :** Traitement asynchrone découplé
✅ **Redis :** Cache distribué potentiel
✅ **Stateless API :** Backend RESTful sans session (Sanctum tokens)
✅ **Docker :** Conteneurisation facilitant scaling horizontal

### Goulots d'étranglement identifiés

⚠️ **Base de données unique :** MariaDB single-node
⚠️ **Storage local :** Fichiers audio/documents en volume Docker local
⚠️ **Queue worker unique :** 1 seul worker par défaut
⚠️ **API OpenAI :** Rate limits + coûts variables
⚠️ **Pyannote processing :** CPU-intensive, pas de GPU
⚠️ **Absence de load balancer :** Containers backend/frontend single-instance

## Patterns & Principes Appliqués

- **SOLID :** Dependency Injection, Single Responsibility
- **Repository Pattern :** Abstraction BDD via Eloquent
- **Service Layer :** Logique métier dans Services (pas dans Controllers)
- **Strategy Pattern :** Extracteurs IA interchangeables
- **Observer Pattern :** Model Events (booted, creating, updating)
- **Factory Pattern :** Eloquent factories pour tests
- **Scope Pattern :** TeamScope pour multi-tenancy
- **Resource Pattern :** API Resources pour transformer JSON

## Environnement de Développement

### Variables d'environnement critiques

```bash
# Laravel
APP_KEY=                    # Clé encryption (artisan key:generate)
APP_ENV=local               # local | production
APP_DEBUG=true              # false en production

# Base de données
DB_CONNECTION=mysql
DB_HOST=db                  # Nom service Docker
DB_DATABASE=courtier-whisper
DB_PASSWORD=                # Fort en production

# Redis
REDIS_HOST=redis            # Nom service Docker
QUEUE_CONNECTION=redis
CACHE_STORE=redis

# IA
OPENAI_API_KEY=sk-proj-... # Clé API OpenAI
HUGGINGFACE_TOKEN=hf_...   # Token HuggingFace pour Pyannote
TRANSCRIPTION_MODE=openai  # openai | whisper_local

# Octane
OCTANE_SERVER=swoole
OCTANE_MAX_REQUESTS=500
```

### Commandes de démarrage

```bash
# Construction et démarrage
docker compose up -d --build

# Migrations
docker compose exec backend php artisan migrate

# Génération clé
docker compose exec backend php artisan key:generate

# Vérifier queues
docker compose exec backend php artisan queue:work redis --verbose

# Logs en temps réel
docker compose logs -f backend
```

## Dépendances Externes

### APIs Tierces
- **OpenAI API :** Whisper (transcription) + GPT-4o-mini (extraction)
- **HuggingFace :** Pyannote.audio (diarisation)

### Coûts estimés par traitement
- **Whisper :** $0.006 / minute audio
- **GPT-4o-mini :** ~$0.0001 / 1K tokens (extraction typique: 2-5K tokens)
- **Pyannote :** Gratuit (self-hosted)

### Alternatives envisageables
- **Whisper local :** Modèle large-v3 (requiert GPU pour performance)
- **Ollama / LLaMA :** LLM open-source local (extraction)
- **Speech-to-Text alternatives :** Google Cloud Speech, Azure Speech

---

**Version :** 1.0
**Date :** 2026-01-02
**Auteur :** Documentation technique pour scaling multi-cabinets
