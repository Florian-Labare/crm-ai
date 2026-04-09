# Guide de Production S3 - CRM Courtier

## Objectif
Ce document recense tous les éléments liés au stockage S3 pour préparer le déploiement sur infrastructure dédiée.

---

## 1. Configuration S3

### Variables d'environnement requises
```env
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=<access_key>
AWS_SECRET_ACCESS_KEY=<secret_key>
AWS_DEFAULT_REGION=eu-west-3
AWS_BUCKET=<bucket_name>
AWS_URL=<public_url>              # Optionnel
AWS_ENDPOINT=<endpoint>           # Pour S3-compatible (MinIO)
AWS_USE_PATH_STYLE_ENDPOINT=false # true pour MinIO, false pour AWS
```

### Fichier de configuration
- `backend/config/filesystems.php:74-86` - Configuration S3

---

## 2. Fichiers stockés sur S3

| Type | Chemin S3 | Modèle | Taille typique |
|------|-----------|--------|----------------|
| Audio uploads | `audio_uploads/` | `AudioRecord` | 5-50 MB |
| Documents conformité | `compliance/{client_id}/` | `ClientComplianceDocument` | 100KB-10MB |
| Documents générés | `documents/` | `GeneratedDocument` | 50KB-2MB |

---

## 3. Stockage local uniquement (PAS sur S3)

| Disk | Chemin | Usage | Action prod |
|------|--------|-------|-------------|
| `templates` | `storage/app/templates/` | Templates DOCX | Inclure dans image Docker |
| `temp` | `storage/app/temp/` | Fichiers temporaires | Volume éphémère |
| `recordings` | `storage/app/recordings/` | Chunks audio | Volume éphémère |

---

## 4. Endpoints API concernés

### Uploads
| Route | Controller | Ligne |
|-------|------------|-------|
| `POST /api/audio/upload` | `AudioController::upload` | api.php:184 |
| `POST /api/clients/{client}/compliance/upload` | `ClientComplianceController::upload` | api.php:166 |
| `POST /api/clients/{client}/compliance/upload-signed` | `ClientComplianceController::uploadSigned` | api.php:167 |

### Downloads
| Route | Controller | Ligne |
|-------|------------|-------|
| `GET /api/documents/{documentId}/download` | `DocumentController::downloadDocument` | api.php:154 |
| `GET /api/clients/{client}/compliance/{document}/download` | `ClientComplianceController::download` | api.php:170 |
| `GET /api/clients/{id}/export/word` | `ExportController::exportWord` | api.php:118 |

---

## 5. Services critiques

| Service | Fichier | Rôle |
|---------|---------|------|
| `StorageService` | `app/Services/StorageService.php` | Abstraction S3 (upload, download, presigned URLs) |
| `AudioService` | `app/Services/AudioService.php:23-50` | Upload audio vers S3 |
| `DocumentGeneratorService` | `app/Services/DocumentGeneratorService.php:108` | Upload documents générés |

---

## 6. Jobs asynchrones

| Job | Fichier | Opérations S3 |
|-----|---------|---------------|
| `ProcessAudioRecording` | `app/Jobs/ProcessAudioRecording.php:130-152` | Download S3 → temp local pour transcription |

---

## 7. Commandes de maintenance

```bash
# Migration local → S3 (existant)
php artisan storage:migrate-to-s3 --dry-run
php artisan storage:migrate-to-s3 --cleanup

# Nettoyage fichiers temp
php artisan audio:cleanup-temp --hours=24

# Purge RGPD audio (30 jours)
php artisan audio:purge-old --days=30
```

---

## 8. Checklist mise en production

### Infrastructure AWS/S3
- [ ] Créer bucket S3 dédié (région: eu-west-3 recommandé)
- [ ] Configurer bucket policy (accès privé uniquement)
- [ ] Configurer CORS si accès frontend direct nécessaire
- [ ] Lifecycle policies pour expiration automatique (optionnel)
- [ ] Créer IAM user avec permissions limitées:
  ```json
  {
    "Effect": "Allow",
    "Action": ["s3:GetObject", "s3:PutObject", "s3:DeleteObject", "s3:ListBucket"],
    "Resource": ["arn:aws:s3:::BUCKET_NAME", "arn:aws:s3:::BUCKET_NAME/*"]
  }
  ```

### Configuration Laravel
- [ ] Définir toutes les variables AWS_* dans `.env` production
- [ ] Vérifier `FILESYSTEM_DISK=s3`
- [ ] Tester connexion S3 avec `php artisan storage:migrate-to-s3 --dry-run`

### Volumes Docker/Kubernetes
- [ ] `storage/app/temp/` → Volume éphémère (emptyDir)
- [ ] `storage/app/recordings/` → Volume éphémère (emptyDir)
- [ ] `storage/app/templates/` → Inclus dans image OU ConfigMap/Volume persistant

### Migration données existantes
- [ ] Backup base de données
- [ ] Exécuter migration: `php artisan storage:migrate-to-s3`
- [ ] Vérifier intégrité des fichiers migrés
- [ ] Nettoyer fichiers locaux: `php artisan storage:migrate-to-s3 --cleanup`

### Cron jobs production
```cron
# Nettoyage fichiers temporaires (quotidien)
0 3 * * * php artisan audio:cleanup-temp --hours=24

# Purge RGPD audio (hebdomadaire)
0 4 * * 0 php artisan audio:purge-old --days=30
```

### Sécurité
- [ ] Bucket S3 en accès privé (pas de public access)
- [ ] Utiliser presigned URLs pour downloads (`StorageService::getTemporaryUrl()`)
- [ ] Rotation credentials IAM régulière
- [ ] Activer versioning S3 (optionnel, pour recovery)
- [ ] Activer encryption at rest (SSE-S3 ou SSE-KMS)

---

## 9. Points d'attention

1. **Mémoire**: `Storage::download()` et `file_get_contents()` chargent en mémoire - attention aux gros fichiers audio

2. **Templates DOCX**: Doivent être accessibles localement pour `DocumentGeneratorService` - ne pas migrer vers S3

3. **Gotenberg**: Le service de conversion PDF nécessite accès réseau au container Gotenberg

4. **Presigned URLs**: Durée par défaut 60 min dans `StorageService::getTemporaryUrl()`

5. **Queue Redis**: Les jobs `ProcessAudioRecording` nécessitent Redis fonctionnel

---

## 10. Tests de validation

```bash
# 1. Tester upload audio
curl -X POST /api/audio/upload -F "audio=@test.wav"

# 2. Tester upload document conformité
curl -X POST /api/clients/1/compliance/upload -F "file=@doc.pdf" -F "document_type=identity"

# 3. Tester génération document
curl -X POST /api/clients/1/documents/generate -d '{"template_id": 1}'

# 4. Vérifier fichiers sur S3
aws s3 ls s3://BUCKET_NAME/audio_uploads/
aws s3 ls s3://BUCKET_NAME/compliance/
aws s3 ls s3://BUCKET_NAME/documents/
```

---

## 11. Script de vérification

Un script de vérification est disponible pour valider la configuration S3:

```bash
php backend/scripts/check-s3-config.php
```

Ce script vérifie:
- Présence des variables d'environnement
- Connexion au bucket S3
- Permissions d'écriture/lecture/suppression
- Configuration des disks Laravel

---

## Fichiers clés à consulter

- `backend/config/filesystems.php` - Configuration disks
- `backend/app/Services/StorageService.php` - Service abstraction S3
- `backend/app/Console/Commands/MigrateStorageToS3.php` - Migration existante
- `backend/app/Console/Commands/CleanupTempFiles.php` - Nettoyage
- `backend/app/Console/Commands/PurgeOldAudioRecords.php` - Purge RGPD
- `backend/scripts/check-s3-config.php` - Script de vérification
