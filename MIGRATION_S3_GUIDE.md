# Guide de Migration S3 - Étape par Étape

## Objectif
Migrer tous les fichiers du stockage local vers S3 (MinIO en dev, AWS en prod) et minimiser l'empreinte locale.

---

## Prérequis

- Docker et Docker Compose installés
- Les services doivent être démarrés (`docker-compose up -d`)
- MinIO doit être accessible sur http://localhost:9001
- La base de données doit être à jour

---

## Architecture cible

### Sur S3/MinIO (cloud)
```
s3://crm-ai-bucket/
├── audio_uploads/          # Fichiers audio uploadés
├── compliance/{client_id}/ # Documents conformité
├── documents/              # Documents générés
└── imports/                # Fichiers d'import CSV/Excel
```

### En local (minimum requis)
```
storage/app/
├── templates/    # Templates DOCX (OBLIGATOIRE pour génération)
├── temp/         # Fichiers temporaires (éphémère)
└── recordings/   # Chunks audio ffmpeg (éphémère)
```

---

## Étapes d'exécution

### 1. Démarrer MinIO

```bash
# Démarrer tous les services Docker
docker-compose up -d

# Vérifier que MinIO est démarré
docker-compose ps minio

# Le bucket 'crm-ai-bucket' devrait être créé automatiquement
# par le service 'minio-setup' dans docker-compose.yml
```

**Vérification:**
- Accéder à http://localhost:9001
- Login: `minioadmin` / `minioadmin`
- Vérifier que le bucket `crm-ai-bucket` existe

---

### 2. Vérifier la configuration S3

```bash
# Exécuter le script de vérification
php backend/scripts/check-s3-config.php
```

**Attendu:**
- ✅ Toutes les variables d'environnement sont définies
- ✅ Connexion S3 réussie
- ✅ Permissions PutObject, GetObject, DeleteObject OK

**En cas d'erreur:**
- Vérifier que MinIO est démarré
- Vérifier le fichier `backend/.env`:
  - `FILESYSTEM_DISK=s3`
  - `AWS_BUCKET=crm-ai-bucket`
  - `AWS_ENDPOINT=http://minio:9000`
  - `AWS_USE_PATH_STYLE_ENDPOINT=true`

---

### 3. Générer le rapport de pré-migration

```bash
# Analyser l'état actuel du stockage
php backend/scripts/pre-migration-report.php
```

**Ce script va:**
- Scanner tous les répertoires locaux
- Compter les fichiers et calculer leur taille
- Identifier les fichiers orphelins (non référencés en DB)
- Estimer l'espace qui sera libéré
- Sauvegarder un rapport JSON dans `storage/logs/pre-migration-report.json`

**Exemple de sortie:**
```
📊 Rapport de Pré-Migration S3
======================================================================

1️⃣  Test de connexion S3/MinIO...
   ✅ Connexion S3 fonctionnelle

2️⃣  Scan du stockage local...
   templates       : 15 fichiers, 50.23 MB
   private         : 4 fichiers, 5.12 MB
   public          : 0 fichiers, 0 B
   recordings      : 2 fichiers, 88 KB
   temp            : 1 fichiers, 4 KB

3️⃣  Références en base de données...
   AudioRecord                    : 0 enregistrements
   ClientComplianceDocument       : 3 enregistrements
   GeneratedDocument              : 1 enregistrements
   ImportSession                  : 3 enregistrements

   Total: 7 fichiers référencés

4️⃣  Détection des fichiers orphelins...
   Fichiers orphelins détectés : 0 (0 B)

5️⃣  Estimation de migration...
   À migrer vers S3       : 5.12 MB (private + public)
   À conserver localement : 50.31 MB (templates + temp + recordings)
   Fichiers orphelins     : 0 B
   Espace libéré (net)    : 5.12 MB

6️⃣  Résumé et recommandations:
----------------------------------------------------------------------
   ✅ Prêt pour la migration

   📝 Étapes recommandées:
      1. Backup de la base de données
      2. Migration test : php artisan storage:migrate-to-s3 --dry-run
      3. Migration réelle : php artisan storage:migrate-to-s3
      4. Vérifier l'application fonctionne correctement
      5. Cleanup local : php artisan storage:migrate-to-s3 --cleanup
```

---

### 4. Backup de la base de données (IMPORTANT!)

```bash
# Backup via Docker
docker-compose exec db mysqldump -u root -proot courtier-whisper > backup_pre_migration_$(date +%Y%m%d).sql

# Vérifier que le backup existe
ls -lh backup_pre_migration_*.sql
```

---

### 5. Migration en mode test (dry-run)

```bash
# Test de migration SANS modifier les fichiers
php artisan storage:migrate-to-s3 --dry-run
```

**Ce que fait cette commande:**
- ✅ Vérifie la connexion S3
- ✅ Liste tous les fichiers qui seraient migrés
- ✅ Calcule l'espace total
- ❌ NE MIGRE PAS réellement les fichiers
- ❌ NE SUPPRIME RIEN

**Vérifier la sortie:**
- Le nombre de fichiers détectés correspond au rapport
- Aucune erreur de connexion
- Les chemins des fichiers sont corrects

---

### 6. Migration réelle

```bash
# Migrer tous les fichiers vers S3
php artisan storage:migrate-to-s3
```

**Ce que fait cette commande:**
- 📤 Upload tous les fichiers vers S3/MinIO
- 📊 Affiche une barre de progression
- ✅ Conserve les fichiers locaux (pas de suppression)
- 📝 Log les résultats

**Vérification:**
```bash
# Lister les fichiers sur MinIO
docker exec minio mc ls local/crm-ai-bucket --recursive
```

---

### 7. Tester l'application

**AVANT de supprimer les fichiers locaux, TESTER l'application:**

1. **Tester la génération de documents:**
   - Créer un nouveau document
   - Vérifier qu'il est accessible
   - Vérifier qu'il est bien sur S3

2. **Tester l'upload de documents de compliance:**
   - Uploader un document
   - Vérifier qu'il est sauvegardé sur S3
   - Vérifier qu'il est téléchargeable

3. **Tester l'import CSV:**
   - Importer un fichier CSV
   - Vérifier que l'import fonctionne
   - Vérifier que le fichier est sur S3

4. **Vérifier les fichiers sur MinIO:**
   ```bash
   # Console web: http://localhost:9001
   # Ou via CLI:
   docker exec minio mc ls local/crm-ai-bucket --recursive
   ```

**Si tout fonctionne:** ✅ Passez à l'étape 8

**Si des erreurs:** ❌ Ne pas supprimer les fichiers locaux, investiguer

---

### 8. Cleanup local (optionnel)

**⚠️ ATTENTION: Cette étape supprime les fichiers locaux!**

```bash
# Supprimer les fichiers locaux après migration
php artisan storage:migrate-to-s3 --cleanup
```

**Ce que fait cette commande:**
- 🗑️ Supprime les fichiers locaux déjà migrés vers S3
- 🛡️ Conserve les templates, temp, recordings (toujours locaux)
- 📝 Log les suppressions

**Vérification:**
```bash
# Vérifier l'espace libéré
du -sh backend/storage/app/*/

# Devrait afficher:
# 50M   backend/storage/app/templates/   (conservé)
# 0-4K  backend/storage/app/temp/        (éphémère)
# 0-88K backend/storage/app/recordings/  (éphémère)
# 0     backend/storage/app/private/     (migré vers S3)
# 0     backend/storage/app/public/      (migré vers S3)
```

---

### 9. Nettoyer les fichiers orphelins (optionnel)

```bash
# Lister les fichiers orphelins
php artisan storage:cleanup-orphans --dry-run

# Supprimer les fichiers orphelins
php artisan storage:cleanup-orphans --delete
```

**Fichiers orphelins:** Fichiers présents sur le disque mais non référencés en base de données.

---

## Commandes utiles

### Vérifier la configuration
```bash
php backend/scripts/check-s3-config.php
```

### Rapport pré-migration
```bash
php backend/scripts/pre-migration-report.php
```

### Migration
```bash
# Test
php artisan storage:migrate-to-s3 --dry-run

# Migration complète
php artisan storage:migrate-to-s3

# Migration d'un type spécifique
php artisan storage:migrate-to-s3 --type=compliance
php artisan storage:migrate-to-s3 --type=documents
php artisan storage:migrate-to-s3 --type=imports
php artisan storage:migrate-to-s3 --type=audio

# Migration + cleanup
php artisan storage:migrate-to-s3 --cleanup
```

### Nettoyage orphelins
```bash
php artisan storage:cleanup-orphans --dry-run
php artisan storage:cleanup-orphans --delete
php artisan storage:cleanup-orphans --delete --force
```

### MinIO CLI
```bash
# Lister les buckets
docker exec minio mc ls local/

# Lister les fichiers
docker exec minio mc ls local/crm-ai-bucket --recursive

# Copier un fichier depuis MinIO
docker exec minio mc cp local/crm-ai-bucket/path/to/file.pdf /tmp/

# Supprimer un fichier
docker exec minio mc rm local/crm-ai-bucket/path/to/file.pdf
```

---

## En cas de problème

### Erreur: Connexion S3 échouée

**Cause:** MinIO n'est pas démarré ou mal configuré

**Solution:**
```bash
# Vérifier les services
docker-compose ps

# Redémarrer MinIO
docker-compose restart minio minio-setup

# Vérifier les logs
docker-compose logs minio
```

### Erreur: Bucket n'existe pas

**Solution:**
```bash
# Créer le bucket manuellement
docker exec minio mc mb local/crm-ai-bucket
```

### Erreur: Fichiers non trouvés

**Cause:** Les chemins en base de données ne correspondent pas aux fichiers réels

**Solution:**
- Vérifier le rapport pré-migration
- Vérifier les chemins en base de données
- Adapter la logique de recherche dans MigrateStorageToS3.php

### Restaurer après migration ratée

```bash
# Restaurer la base de données
docker-compose exec -T db mysql -u root -proot courtier-whisper < backup_pre_migration_YYYYMMDD.sql

# Les fichiers locaux sont toujours présents si vous n'avez pas utilisé --cleanup
```

---

## Migration vers AWS S3 en production

Pour passer de MinIO (dev) à AWS S3 (prod):

1. **Créer un bucket S3 sur AWS**

2. **Mettre à jour `.env` en production:**
   ```env
   FILESYSTEM_DISK=s3
   AWS_ACCESS_KEY_ID=AKIAXXXXXXXXXXXXXXXX
   AWS_SECRET_ACCESS_KEY=your-secret-key
   AWS_DEFAULT_REGION=eu-west-3  # Paris
   AWS_BUCKET=crm-ai-production
   AWS_ENDPOINT=  # Laisser vide pour AWS
   AWS_USE_PATH_STYLE_ENDPOINT=false
   ```

3. **Copier les fichiers de MinIO vers AWS S3:**
   ```bash
   # Depuis MinIO local
   docker exec minio mc alias set aws https://s3.amazonaws.com ACCESS_KEY SECRET_KEY
   docker exec minio mc mirror local/crm-ai-bucket aws/crm-ai-production
   ```

4. **Tester en production**

---

## Checklist finale

- [ ] MinIO démarré et accessible
- [ ] Configuration S3 vérifiée (`check-s3-config.php`)
- [ ] Rapport de pré-migration généré
- [ ] Backup de la base de données effectué
- [ ] Migration test (dry-run) réussie
- [ ] Migration réelle effectuée
- [ ] Application testée (documents, uploads, imports)
- [ ] Fichiers vérifiés sur MinIO
- [ ] Cleanup local effectué (optionnel)
- [ ] Fichiers orphelins nettoyés (optionnel)

---

## Support

En cas de problème, vérifier:
1. Les logs Docker: `docker-compose logs minio backend`
2. Les logs Laravel: `backend/storage/logs/laravel.log`
3. Le rapport de pré-migration: `backend/storage/logs/pre-migration-report.json`
