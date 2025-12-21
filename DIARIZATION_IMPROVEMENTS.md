# Améliorations du système de diarisation

Ce document décrit les améliorations apportées au système d'enregistrement vocal et de transcription pour renforcer la robustesse de la fonctionnalité de diarisation (séparation courtier/client).

## Fonctionnalités ajoutées

### 1. Health-check Pyannote

**Objectif**: Vérifier automatiquement la disponibilité du système de diarisation au démarrage et à la demande.

#### Composants Backend

- **`PyannoteHealthService.php`**: Service de vérification de santé avec cache
- **`check_pyannote.py`**: Script Python qui vérifie torch, pyannote, et l'accès au modèle
- **`CheckPyannoteHealth.php`**: Commande artisan `php artisan pyannote:health`
- **`HealthController.php`**: Endpoints API pour le monitoring

#### Endpoints API

```
GET /api/health/audio          # Statut global du système audio
GET /api/health/pyannote       # Statut détaillé de pyannote
GET /api/health/pyannote?refresh=true  # Force une nouvelle vérification
```

#### Commandes Artisan

```bash
# Vérifier la santé de pyannote
php artisan pyannote:health

# Forcer une vérification fraîche
php artisan pyannote:health --refresh

# Sortie JSON
php artisan pyannote:health --json
```

#### Configuration

Dans `.env`:
```env
HUGGINGFACE_TOKEN=hf_xxx          # Token requis pour pyannote
PYANNOTE_CHECK_ON_BOOT=false      # true en production
```

---

### 2. Correction manuelle des speakers

**Objectif**: Permettre aux utilisateurs de corriger l'identification automatique courtier/client.

#### Composants Backend

- **Migration `add_diarization_fields_to_audio_records`**: Ajoute les champs:
  - `diarization_data`: Données brutes de la diarisation
  - `speaker_corrections`: Corrections manuelles appliquées
  - `diarization_success`: Statut de succès
  - `speakers_corrected`: Flag de correction
  - `corrected_at`, `corrected_by`: Métadonnées

- **`SpeakerCorrectionController.php`**: Controller pour la gestion des corrections

#### Endpoints API

```
GET  /api/audio-records/{id}/speakers           # Voir les speakers détectés
POST /api/audio-records/{id}/speakers/correct   # Corriger un speaker
POST /api/audio-records/{id}/speakers/correct-batch  # Corriger plusieurs speakers
POST /api/audio-records/{id}/speakers/reset     # Réinitialiser les corrections
GET  /api/audio-records/needs-review            # Liste des enregistrements à vérifier
```

#### Composants Frontend

- **`SpeakerCorrection.tsx`**: Interface de correction des speakers
  - Affiche les speakers détectés avec statistiques
  - Permet de changer le rôle (courtier ↔ client)
  - Sauvegarde en batch
  - Réinitialisation possible

---

### 3. Monitoring des échecs de diarisation

**Objectif**: Collecter des métriques et alerter sur les problèmes de diarisation.

#### Composants Backend

- **Migration `create_diarization_logs_table`**: Table de logs avec:
  - Statut (success, failed, timeout, fallback, skipped)
  - Métriques de performance (durée, taille fichier)
  - Résultats (speakers détectés, durées, segments)
  - Erreurs (message, code)

- **`DiarizationLog.php`**: Modèle avec scopes pour le monitoring
- **`DiarizationMonitoringService.php`**: Service de logging et statistiques
- **`DiarizationStats.php`**: Commande artisan

#### Endpoints API

```
GET /api/diarization/stats?days=7   # Statistiques sur N jours
```

#### Commandes Artisan

```bash
# Voir les statistiques
php artisan diarization:stats

# Sur 30 jours
php artisan diarization:stats --days=30

# Sortie JSON
php artisan diarization:stats --json
```

#### Composants Frontend

- **`PyannoteHealthStatus.tsx`**: Widget de statut (compact ou détaillé)
- **`DiarizationStats.tsx`**: Dashboard de statistiques
  - Taux de succès
  - Activité journalière (graphique)
  - Top erreurs
  - Échecs récents

---

## Intégration

### DiarizationService amélioré

Le service existant a été enrichi avec:

```php
// Diarisation avec monitoring automatique
$result = $diarizationService->diarizeWithMonitoring($audioPath, [
    'audio_record_id' => $audioRecord->id,
    'team_id' => $teamId,
    'user_id' => $userId,
]);

// Mise à jour de l'AudioRecord
$diarizationService->updateAudioRecordWithDiarization($audioRecord, $result);
```

### AudioServiceProvider

Enregistre automatiquement les services et peut vérifier pyannote au boot:

```php
// bootstrap/providers.php
App\Providers\AudioServiceProvider::class,
```

---

## Migrations à exécuter

```bash
# Via Docker
docker-compose exec app php artisan migrate

# Ou localement
php artisan migrate
```

Tables créées:
- `diarization_logs`: Historique des diarisations
- Colonnes ajoutées à `audio_records`

---

## Utilisation des composants React

```tsx
import { SpeakerCorrection } from './components/SpeakerCorrection';
import { PyannoteHealthStatus } from './components/PyannoteHealthStatus';
import { DiarizationStats } from './components/DiarizationStats';

// Widget de statut compact
<PyannoteHealthStatus compact />

// Statut détaillé
<PyannoteHealthStatus showDetails />

// Correction des speakers pour un enregistrement
<SpeakerCorrection
  audioRecordId={123}
  onCorrectionApplied={() => refetch()}
/>

// Dashboard de monitoring
<DiarizationStats defaultDays={7} />
```

---

## Recommandations de production

1. **Activer le check au démarrage**:
   ```env
   PYANNOTE_CHECK_ON_BOOT=true
   ```

2. **Mettre en place des alertes** sur:
   - Taux de succès < 80%
   - 3+ échecs consécutifs
   - Temps de traitement > 5 minutes

3. **Nettoyer les logs** périodiquement:
   ```sql
   DELETE FROM diarization_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
   ```

4. **Surveiller l'endpoint de santé**:
   ```bash
   curl -s http://localhost:8000/api/health/audio | jq '.status'
   ```
