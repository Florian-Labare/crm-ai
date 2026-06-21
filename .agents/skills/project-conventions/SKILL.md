---
name: project-conventions
description: Architecture et conventions du CRM courtier. Utiliser automatiquement lors de toute modification de code backend Laravel ou frontend React.
user-invocable: false
---

# Conventions du projet CRM Courtier

## Stack technique

- **Backend** : Laravel 12, PHP 8.3, MySQL, Redis, Swoole (Octane)
- **Frontend** : React 18 + TypeScript, Tailwind CSS, Vite
- **Infra** : Docker Compose (laravel_app, db, redis, minio, gotenberg, pyannote)
- **Storage** : S3 via MinIO (local) / AWS S3 (prod). Default disk = `s3`
- **IA** : OpenAI Whisper (transcription), GPT-4 (analyse), Pyannote (diarisation)

## Structure backend

```
backend/
  app/
    Http/Controllers/     # Controllers REST
    Models/               # Eloquent models avec team_id scope
    Services/             # Logique metier
    Jobs/                 # Jobs async (Redis queue)
    Observers/            # Model observers (cascade delete, audit)
    Console/Commands/     # Artisan commands
  config/
    filesystems.php       # Disks: s3, temp, templates, recordings, local
  routes/api.php          # Toutes les routes API
  database/migrations/
```

## Structure frontend

```
frontend/src/
  pages/                  # Pages principales (HomePage, ClientDetailPage, etc.)
  components/             # Composants reutilisables prefix Vuexy*
  api/apiClient.ts        # Axios instance avec auth token
  contexts/AuthContext.tsx # Auth context (JWT)
  types/api.ts            # Types TypeScript
```

## Conventions backend

- **Multi-tenant** : Toutes les tables principales ont `team_id`. Utiliser le scope global `TeamScope`.
- **API responses** : `{ success: true, data: ... }` ou `{ success: false, message: "..." }`
- **Storage** :
  - Fichiers persistants (audio, compliance, docs) -> disk S3 par defaut (`Storage::put()`)
  - Fichiers temporaires (ffmpeg, diarisation) -> disk `temp` (`Storage::disk('temp')`)
  - Templates DOCX -> disk `templates` (`Storage::disk('templates')`)
  - Chunks audio -> disk `recordings` (`Storage::disk('recordings')`)
- **Logs** : `Log::info('[MODULE] Message', ['context' => ...])` avec prefixe module
- **RGPD** : Toute suppression de donnees personnelles doit etre tracee via `AuditService`

## Conventions frontend

- **Design system** : Style Vuexy avec palette :
  - Primary : `#7367F0`
  - Success : `#28C76F`
  - Warning : `#FF9F43`
  - Danger : `#EA5455`
  - Text : `#5E5873` (titre), `#6E6B7B` (body), `#B9B9C3` (muted)
  - Border : `#EBE9F1`
  - Background : `#F8F8F8`
- **Cards** : `className="vx-card"` (bg-white, rounded-xl, shadow, border, padding)
- **API calls** : Utiliser `api` depuis `../api/apiClient` (jamais `axios` directement)
- **State** : `useState` + `useEffect` local, pas de state manager global
- **Composants** : Prefix `Vuexy` pour les composants design system (`VuexyStatCard`, `VuexyTabs`, etc.)
- **Icons** : `lucide-react` uniquement

## Modeles principaux

- `Client` : donnees client (nom, prenom, adresse, besoins[], etc.)
- `Conjoint` : conjoint du client (relation hasOne)
- `AudioRecord` : enregistrements audio + transcription
- `RecordingSession` : sessions d'enregistrement longs (chunks)
- `GeneratedDocument` : documents generes (DOCX/PDF)
- `ClientComplianceDocument` : documents de compliance uploades (CNI, RIB, etc.)
- `ComplianceRequirement` : documents requis par besoin (global, prevoyance, etc.)
- `ImportSession` : sessions d'import de donnees externes
- `PendingChange` : modifications en attente de validation (mode review)

## Docker

- Container principal : `laravel_app`
- Commandes artisan : `docker exec laravel_app php artisan <command>`
- Config cache : attention, `config:cache` peut echouer (closure dans config), utiliser `config:clear`
