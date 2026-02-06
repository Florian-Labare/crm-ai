# Inventaire du Stockage S3 - Application CRM Courtier

**Date:** 2026-02-05
**Bucket S3:** `crm-ai-bucket`
**Disk par défaut:** `s3`

---

## 📦 Types de Fichiers dans S3

### 1. Documents Générés 📄

**Description:** Documents DOCX/PDF générés à partir de templates et données clients.

**Chemin S3:** `documents/`

**Format:**
```
documents/{client_nom}_{client_prenom}_{template_nom}_{timestamp}.{docx|pdf}

Exemple:
documents/huck_guillaume_mandat_sante_prevoyance_20260205_194419.docx
documents/dupont_marie_questionnaire_retraite_20260205_120000.pdf
```

**Référence DB:**
- Table: `generated_documents`
- Colonne: `file_path`
- Relation: `client_id`, `document_template_id`, `user_id`

**Code source:**
- Service: `app/Services/DocumentGeneratorService.php:108`
- Ligne: `Storage::put($s3Path, file_get_contents($finalTempPath));`

**Processus:**
1. Template DOCX chargé depuis `storage/app/templates/` (local)
2. Variables remplacées avec données client
3. Document généré temporairement dans `storage/app/temp/`
4. Upload vers S3 `documents/`
5. Fichier temporaire supprimé

**Formats supportés:**
- `.docx` - Format Word par défaut
- `.pdf` - Converti via Gotenberg

**Volume estimé:** Variable selon utilisation (1-5 MB par document)

---

### 2. Documents de Compliance 📋

**Description:** Documents administratifs uploadés par les utilisateurs (CNI, justificatifs, contrats, etc.).

**Chemin S3:** `compliance/{client_id}/`

**Format:**
```
compliance/{client_id}/{filename}

Exemple:
compliance/3629/carte_identite_recto.pdf
compliance/3629/justificatif_domicile.pdf
compliance/1/contrat_signe.pdf
```

**Référence DB:**
- Table: `client_compliance_documents`
- Colonnes: `file_path`, `document_type`, `category`
- Relation: `client_id`, `requirement_id`

**Code source:**
- Controller: `app/Http/Controllers/ClientComplianceController.php:394`
- Ligne: `$path = $file->store("compliance/{$client->id}");`

**Types de documents:**
- `identity_card` - Carte d'identité
- `proof_of_address` - Justificatif de domicile
- `tax_notice` - Avis d'imposition
- `rib` - RIB
- `signed_document` - Documents signés
- `other` - Autres documents

**Catégories:**
- Identité
- Administratif
- Fiscal
- Bancaire
- Contrats
- Divers

**Volume estimé:** 0.5-2 MB par document (PDF/images)

---

### 3. Fichiers d'Import 📊

**Description:** Fichiers CSV/Excel uploadés pour import de données clients ou externes.

**Chemin S3:** `imports/`

**Format:**
```
imports/{timestamp}_{filename}

Exemple:
imports/1768064265_Clients_SOCOGEA.csv
imports/1768587619_export_clients.xlsx
imports/1770275074_import_prospects.csv
```

**Référence DB:**
- Table: `import_sessions`
- Colonne: `file_path`
- Relation: `team_id`, `user_id`

**Code source:**
- Controller: `app/Http/Controllers/ImportSessionController.php:51`
- Ligne: `$path = $file->storeAs('imports', $filename);`

**Statuts d'import:**
- `pending` - En attente d'analyse
- `analyzing` - Analyse en cours
- `ready_to_map` - Prêt pour mapping
- `mapped` - Mapping effectué
- `processing` - Import en cours
- `completed` - Import terminé
- `failed` - Import échoué

**Formats supportés:**
- `.csv` - CSV (encodage UTF-8, Latin1, Windows-1252)
- `.xlsx` - Excel
- `.xls` - Excel ancien format

**Volume estimé:** 0.5-5 MB par fichier

**Conservation:** Les fichiers sont conservés pour traçabilité et possibilité de ré-import

---

### 4. Fichiers Audio (à venir) 🎙️

**Description:** Enregistrements audio des réunions/rendez-vous clients (fonctionnalité partiellement implémentée).

**Chemin S3:** `audio_uploads/`

**Format:**
```
audio_uploads/{filename}

Exemple:
audio_uploads/meeting_client_3629_20260205.webm
audio_uploads/rdv_huck_guillaume.mp3
```

**Référence DB:**
- Table: `audio_records`
- Colonne: `path`
- Relation: `client_id`, `team_id`, `user_id`

**Code source:**
- Service: `app/Services/AudioService.php:35`
- Ligne: `$path = $audioFile->store('audio_uploads', 'public');`

**⚠️ Note importante:**
Le code actuel utilise `store('audio_uploads', 'public')` qui stocke sur le disk 'public' (local).
Pour utiliser S3, il faudrait modifier en:
```php
$path = $audioFile->store('audio_uploads');  // Utilise le disk par défaut (s3)
// OU
$path = $audioFile->store('audio_uploads', 's3');  // Explicite
```

**Formats supportés:**
- `.webm` - Format web standard
- `.mp3` - Format audio compressé
- `.wav` - Format audio non compressé

**Volume estimé:** 5-50 MB par enregistrement (selon durée et qualité)

**Statuts:**
- `pending` - En attente de traitement
- `transcribing` - Transcription en cours
- `analyzing` - Analyse en cours
- `completed` - Traitement terminé
- `failed` - Traitement échoué

---

## 🚫 Fichiers qui RESTENT Locaux

### 1. Templates de Documents 📝

**Localisation:** `storage/app/templates/`

**Description:** Templates DOCX utilisés pour générer les documents clients.

**Disk Laravel:** `templates` (local)

**Raison:**
- Doivent être accessibles en lecture rapide pour génération
- Utilisés par PhpWord TemplateProcessor qui nécessite un chemin local
- Pas de modifications fréquentes, pas besoin de cloud

**Référence DB:**
- Table: `document_templates`
- Colonne: `file_path`

**Volume:** ~50 MB (18 fichiers)

**Exemples:**
```
templates/Mandat Santé Prévoyance Épargne Retraite ADE PP.docx
templates/Questionnaire Retraite.docx
templates/Fiche Client.docx
```

---

### 2. Fichiers Temporaires ⏱️

**Localisation:** `storage/app/temp/`

**Description:** Fichiers temporaires durant le traitement (génération DOCX, conversion PDF, etc.).

**Disk Laravel:** `temp` (local)

**Sous-dossiers:**
- `temp/documents/` - Fichiers temporaires de génération de documents
- `temp/imports/` - Copies temporaires de fichiers d'import pour traitement

**Raison:**
- Fichiers éphémères (supprimés après traitement)
- Performances (accès local plus rapide)
- Pas de valeur à long terme

**Cycle de vie:** Créé → Traité → Supprimé (quelques secondes à quelques minutes)

**Volume:** ~260 B - 5 MB (variable)

---

### 3. Chunks d'Enregistrement Audio 🎤

**Localisation:** `storage/app/recordings/`

**Description:** Morceaux d'enregistrement audio reçus par chunk durant l'enregistrement en temps réel.

**Disk Laravel:** `recordings` (local)

**Format:**
```
recordings/{session_id}/{session_id}_part_{index}.webm

Exemple:
recordings/abc123/abc123_part_0.webm
recordings/abc123/abc123_part_1.webm
recordings/abc123/abc123_part_2.webm
```

**Code source:**
- Service: `app/Services/RecordingService.php:62`
- Ligne: `$path = $audio->storeAs("{$sessionId}", $filename, 'recordings');`

**Raison:**
- Fichiers temporaires (utilisés pour assemblage puis supprimés)
- Traitement en temps réel nécessite accès local rapide
- Supprimés automatiquement après assemblage final

**Cycle de vie:**
1. Réception des chunks en temps réel
2. Stockage temporaire
3. Assemblage en un fichier final via FFmpeg
4. Nettoyage automatique (méthode `cleanupRecordingDirectory()`)

**Volume:** ~85 KB - 5 MB (selon durée d'enregistrement)

---

## 📊 Résumé des Chemins S3

### Structure actuelle (post-migration)

```
s3://crm-ai-bucket/
├── documents/                          # ✅ Documents générés (DOCX/PDF)
│   ├── client1_template1_20260205.docx
│   ├── client2_template2_20260205.pdf
│   └── ...
│
├── compliance/                         # ✅ Documents de compliance
│   ├── 1/                              # Client ID 1
│   │   ├── cni_recto.pdf
│   │   ├── justificatif_domicile.pdf
│   │   └── ...
│   ├── 2/                              # Client ID 2
│   │   └── ...
│   └── 3629/                           # Client ID 3629
│       └── ...
│
├── imports/                            # ✅ Fichiers d'import
│   ├── 1768064265_Clients_SOCOGEA.csv
│   ├── 1768587619_export_clients.xlsx
│   └── ...
│
└── audio_uploads/                      # ⚠️ À CORRIGER (actuellement en local)
    ├── meeting_20260205.webm
    └── ...
```

### Structure locale (doit rester)

```
storage/app/
├── templates/                          # 📝 Templates DOCX (50 MB)
│   ├── Mandat Santé.docx
│   ├── Questionnaire Retraite.docx
│   └── ...
│
├── temp/                               # ⏱️ Fichiers temporaires (éphémère)
│   ├── documents/
│   └── imports/
│
└── recordings/                         # 🎤 Chunks audio (éphémère)
    ├── session1/
    │   ├── session1_part_0.webm
    │   ├── session1_part_1.webm
    │   └── ...
    └── ...
```

---

## 🔍 Vérification de l'Inventaire

### Commandes de vérification

```bash
# 1. Lister tous les fichiers sur S3
docker exec minio_storage mc ls minio/crm-ai-bucket --recursive

# 2. Compter les fichiers par type
docker exec minio_storage mc ls minio/crm-ai-bucket/documents/ --recursive | wc -l
docker exec minio_storage mc ls minio/crm-ai-bucket/compliance/ --recursive | wc -l
docker exec minio_storage mc ls minio/crm-ai-bucket/imports/ --recursive | wc -l

# 3. Calculer la taille totale sur S3
docker exec minio_storage mc du minio/crm-ai-bucket

# 4. Vérifier les fichiers locaux
docker-compose exec backend du -sh storage/app/templates/
docker-compose exec backend du -sh storage/app/temp/
docker-compose exec backend du -sh storage/app/recordings/

# 5. Comparer avec la base de données
docker-compose exec backend php artisan tinker --execute="
echo 'Documents générés: ' . \App\Models\GeneratedDocument::count() . PHP_EOL;
echo 'Documents compliance: ' . \App\Models\ClientComplianceDocument::count() . PHP_EOL;
echo 'Fichiers import: ' . \App\Models\ImportSession::whereNotNull('file_path')->count() . PHP_EOL;
echo 'Enregistrements audio: ' . \App\Models\AudioRecord::whereNotNull('path')->count() . PHP_EOL;
"
```

### Vérification de cohérence

```bash
# Générer un rapport complet
docker-compose exec backend php scripts/pre-migration-report.php
```

---

## ⚠️ Points d'Attention

### 1. Fichiers Audio - Correction nécessaire

**Problème actuel:**
```php
// Dans AudioService.php:35
$path = $audioFile->store('audio_uploads', 'public');  // ❌ Stocke en local
```

**Correction à apporter:**
```php
// Option 1: Utiliser le disk par défaut (s3)
$path = $audioFile->store('audio_uploads');

// Option 2: Spécifier explicitement S3
$path = $audioFile->store('audio_uploads', 's3');
```

**Impact:** Les nouveaux enregistrements audio ne sont actuellement PAS sur S3.

---

### 2. Migration des Anciens Fichiers

**Fichiers déjà en local à migrer:**
- Utiliser la commande: `php artisan storage:migrate-to-s3`
- Vérifier avec: `php artisan storage:migrate-to-s3 --dry-run`

---

### 3. Nettoyage des Orphelins

**Fichiers non référencés en DB:**
- Détecter avec: `php artisan storage:cleanup-orphans --dry-run`
- Supprimer avec: `php artisan storage:cleanup-orphans --delete`

---

### 4. Monitoring de l'Espace S3

**Limites MinIO:**
- MinIO refuse d'écrire si < 10% d'espace disque disponible
- Surveiller l'espace Docker: `docker exec minio_storage df -h /data`
- Nettoyer Docker régulièrement: `docker system prune`

---

## 📈 Estimation des Volumes

### Par type de fichier

| Type | Chemin S3 | Taille moyenne | Nombre estimé | Volume total estimé |
|------|-----------|----------------|---------------|---------------------|
| Documents générés | `documents/` | 2 MB | 10-100 | 20-200 MB |
| Compliance | `compliance/{client_id}/` | 1 MB | 50-500 | 50-500 MB |
| Imports | `imports/` | 2 MB | 10-50 | 20-100 MB |
| Audio (futur) | `audio_uploads/` | 20 MB | 10-100 | 200 MB - 2 GB |

### Croissance estimée

- **Par jour:** 10-50 MB (selon activité)
- **Par mois:** 300 MB - 1.5 GB
- **Par an:** 3.6 GB - 18 GB

**Recommandation:** Prévoir minimum 20 GB pour S3 en production.

---

## 🔄 Migration vers AWS S3 (Production)

Lorsque vous passerez en production avec AWS S3 réel:

### 1. Créer le bucket AWS S3
```bash
aws s3 mb s3://crm-ai-production --region eu-west-3
```

### 2. Configurer les variables d'environnement
```env
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=AKIAXXXXXXXXXXXXXXXX
AWS_SECRET_ACCESS_KEY=your-secret-key
AWS_DEFAULT_REGION=eu-west-3
AWS_BUCKET=crm-ai-production
AWS_ENDPOINT=                           # Vide pour AWS
AWS_USE_PATH_STYLE_ENDPOINT=false
```

### 3. Migrer les données de MinIO vers AWS S3
```bash
# Depuis le container MinIO
docker exec minio_storage mc alias set aws https://s3.amazonaws.com ACCESS_KEY SECRET_KEY
docker exec minio_storage mc mirror minio/crm-ai-bucket aws/crm-ai-production
```

### 4. Politique de cycle de vie

Configurer une politique de suppression automatique des vieux fichiers temporaires:
```json
{
  "Rules": [
    {
      "Id": "DeleteOldImports",
      "Prefix": "imports/",
      "Status": "Enabled",
      "Expiration": {
        "Days": 365
      }
    }
  ]
}
```

---

## 📝 Checklist de Vérification

- [ ] Tous les nouveaux documents générés vont sur S3
- [ ] Tous les documents de compliance vont sur S3
- [ ] Tous les fichiers d'import vont sur S3
- [ ] Les fichiers audio sont stockés correctement (à corriger)
- [ ] Les templates restent en local
- [ ] Les fichiers temporaires restent en local
- [ ] Les chunks d'enregistrement restent en local
- [ ] Espace S3 suffisant (> 10% libre)
- [ ] Monitoring de l'espace activé
- [ ] Backup des données critiques en place

---

**Dernière mise à jour:** 2026-02-05
**Version:** 1.0
