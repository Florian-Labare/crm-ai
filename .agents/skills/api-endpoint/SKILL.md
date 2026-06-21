---
name: api-endpoint
description: Creer un nouvel endpoint API REST Laravel. Utiliser automatiquement quand on demande de creer une route, un controller ou un endpoint API.
argument-hint: "[Resource] [action: index|show|store|update|destroy]"
---

# Creer un endpoint API REST

Ressource demandee : $ARGUMENTS

## Etapes obligatoires

### 1. Controller

Creer ou modifier le controller dans `backend/app/Http/Controllers/`.

```php
// Convention de reponse
return response()->json([
    'success' => true,
    'data' => $data,
]);

// Erreur
return response()->json([
    'success' => false,
    'message' => 'Description claire de l\'erreur',
], 422);
```

**Regles :**
- Injecter les modeles via route model binding (`Client $client`)
- Valider les inputs avec `$request->validate([...])` ou une FormRequest
- Toujours inclure `team_id` dans les requetes (scope multi-tenant)
- Logger les actions importantes avec `Log::info('[MODULE] ...')`
- Wrap les operations risquees dans try/catch

### 2. Route

Ajouter dans `backend/routes/api.php` a l'interieur du groupe `auth:sanctum`.

```php
// Convention REST
Route::get('/resources', [ResourceController::class, 'index']);
Route::post('/resources', [ResourceController::class, 'store']);
Route::get('/resources/{resource}', [ResourceController::class, 'show']);
Route::put('/resources/{resource}', [ResourceController::class, 'update']);
Route::delete('/resources/{resource}', [ResourceController::class, 'destroy']);
```

### 3. Validation

Valider tous les inputs utilisateur. Ne jamais faire confiance aux donnees entrantes.

```php
$request->validate([
    'name' => 'required|string|max:255',
    'email' => 'nullable|email',
    'file' => 'nullable|file|mimes:pdf,jpg,png|max:10240',
]);
```

### 4. Storage (si fichiers)

- Upload persistant : `$file->store("dossier/{$client->id}")` (va sur S3)
- Ne jamais utiliser `Storage::disk('public')` -> utiliser le disk par defaut (S3)
- Fichiers temporaires : `Storage::disk('temp')`

### 5. Verifier

Apres creation, verifier que la route est accessible :
```bash
docker exec laravel_app php artisan route:list --path=api/nouveau-endpoint
```
