---
name: import-check
description: Verifier et debugger le pipeline d'import de donnees clients (CSV, Excel, base externe). Utiliser quand l'utilisateur parle d'import, de mapping de colonnes, de donnees externes ou de migration de donnees.
allowed-tools: Bash, Read, Grep, Glob
---

# Verification du pipeline d'import

Contexte : $ARGUMENTS

## Architecture de l'import

```
ImportSessionController (upload/start)
  -> ImportOrchestrationService (orchestration)
    -> ImportMappingService (mapping colonnes -> champs)
      -> ConjointSyncService (sync conjoint)
```

## Fichiers cles

- `backend/app/Http/Controllers/ImportSessionController.php` : API endpoints
- `backend/app/Http/Controllers/ImportMappingController.php` : Gestion des mappings
- `backend/app/Services/Import/ImportOrchestrationService.php` : Orchestration
- `backend/app/Services/Import/ImportMappingService.php` : Mapping colonnes
- `backend/app/Services/ConjointSyncService.php` : Sync conjoint
- `backend/app/Models/ImportSession.php` : Modele session

## Points de verification

### 1. Upload du fichier
- Fichier bien stocke sur S3 (`Storage::put`)
- `ImportSession` creee avec `file_path`, `team_id`, `status`
- Formats acceptes : CSV, XLSX, XLS

### 2. Mapping des colonnes
- `ImportMapping` existe avec les bonnes correspondances
- Champs obligatoires mappes : `nom`, `prenom`
- Preview des donnees coherente

### 3. Traitement des lignes
- Chaque ligne cree/met a jour un `Client`
- `team_id` correctement assigne
- Doublons detectes (nom + prenom + date_naissance)
- Conjoint synchronise si donnees presentes

### 4. RGPD
- Consentement enregistre (`ImportConsent`)
- Base legale selectionnee
- Audit trail complet
- Donnees d'un autre cabinet : anonymisation si necessaire

### 5. Debug

Verifier le statut d'une session :
```bash
docker exec laravel_app php artisan tinker --execute="
\$s = App\Models\ImportSession::latest()->first();
echo 'Status: ' . \$s->status . PHP_EOL;
echo 'File: ' . \$s->file_path . PHP_EOL;
echo 'Rows: ' . \$s->total_rows . PHP_EOL;
echo 'Imported: ' . \$s->imported_rows . PHP_EOL;
echo 'Errors: ' . \$s->error_rows . PHP_EOL;
"
```

Verifier les logs :
```bash
docker exec laravel_app grep -i "import" /var/www/html/storage/logs/laravel.log | tail -30
```
