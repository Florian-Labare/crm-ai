# Refactorisation Laravel Boost - Plan Complet

## ✅ Déjà Fait

1. ✅ Laravel Octane installé et configuré
2. ✅ Laravel Boost installé
3. ✅ ClientResource créé
4. ✅ ConjointResource créé
5. ✅ EnfantResource créé (partiellement)

## 🔨 À Faire

### Phase 1: Compléter les API Resources (30 min)

Les resources restantes à compléter :

```bash
# Fichiers à éditer dans app/Http/Resources/
- AudioRecordResource.php
- BaePrevoyanceResource.php
- BaeRetraiteResource.php
- BaeEpargneResource.php
```

**Template à utiliser** :
```php
<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class XxxResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            // Ajouter tous les champs du modèle
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
```

### Phase 2: Créer les Form Requests manquants (20 min)

```bash
php artisan make:request StoreAudioRequest
php artisan make:request UpdateQuestionnaireRequest
```

**Exemple StoreAudioRequest.php** :
```php
<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAudioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'audio' => 'required|file|mimes:mp3,wav,ogg,webm,m4a,mpeg|max:20480',
            'client_id' => 'nullable|exists:clients,id',
        ];
    }

    public function messages(): array
    {
        return [
            'audio.required' => 'Le fichier audio est requis',
            'audio.max' => 'Le fichier ne doit pas dépasser 20 Mo',
            'client_id.exists' => 'Le client spécifié n\'existe pas',
        ];
    }
}
```

### Phase 3: Refactoriser AudioController (30 min)

**Avant (actuel)** :
```php
public function upload(Request $request): JsonResponse
{
    $request->validate([
        'audio' => 'required|file|...',
    ]);

    // Logique métier ici
    $audioRecord = AudioRecord::create([...]);

    return response()->json([...]);
}
```

**Après (Boost conventions)** :
```php
public function upload(StoreAudioRequest $request): JsonResponse
{
    $audioRecord = $this->audioService->uploadAndProcess(
        $request->file('audio'),
        $request->validated('client_id'),
        auth()->id()
    );

    return AudioRecordResource::make($audioRecord)
        ->response()
        ->setStatusCode(202);
}
```

### Phase 4: Refactoriser ClientController (20 min)

**Changements à faire** :

1. **Dans `index()`** - Ajouter eager loading :
```php
public function index(): JsonResponse
{
    $clients = Client::with(['conjoint', 'enfants'])
        ->where('user_id', auth()->id())
        ->orderByDesc('id')
        ->get();

    return ClientResource::collection($clients)
        ->response();
}
```

2. **Dans `show()`** - Utiliser Resource :
```php
public function show(int $id): JsonResponse
{
    $client = Client::with([
            'conjoint',
            'enfants',
            'santeSouhait',
            'baePrevoyance',
            'baeRetraite',
            'baeEpargne'
        ])
        ->where('user_id', auth()->id())
        ->findOrFail($id);

    return ClientResource::make($client)
        ->response();
}
```

3. **Dans `store()` et `update()`** - Utiliser Resource :
```php
public function store(StoreClientRequest $request): JsonResponse
{
    $client = Client::create(
        array_merge($request->validated(), [
            'user_id' => auth()->id()
        ])
    );

    return ClientResource::make($client)
        ->response()
        ->setStatusCode(201);
}
```

### Phase 5: Créer un AudioService (40 min)

**Créer** : `app/Services/AudioService.php`

```php
<?php

namespace App\Services;

use App\Models\AudioRecord;
use App\Jobs\ProcessAudioRecording;
use Illuminate\Http\UploadedFile;

/**
 * Service de gestion des enregistrements audio
 */
class AudioService
{
    /**
     * Upload et traite un fichier audio
     */
    public function uploadAndProcess(
        UploadedFile $audioFile,
        ?int $clientId,
        int $userId
    ): AudioRecord {
        // Validation de l'accès au client si fourni
        if ($clientId) {
            $this->validateClientAccess($clientId, $userId);
        }

        // Sauvegarde du fichier
        $path = $audioFile->store('audio_uploads', 'public');

        // Création de l'enregistrement
        $audioRecord = AudioRecord::create([
            'user_id' => $userId,
            'path' => $path,
            'status' => 'pending',
            'client_id' => $clientId,
        ]);

        // Dispatch du job
        ProcessAudioRecording::dispatch($audioRecord, $clientId);

        return $audioRecord;
    }

    /**
     * Vérifie que le client appartient à l'utilisateur
     */
    private function validateClientAccess(int $clientId, int $userId): void
    {
        $exists = \App\Models\Client::where('id', $clientId)
            ->where('user_id', $userId)
            ->exists();

        if (!$exists) {
            throw new \Illuminate\Auth\Access\AuthorizationException(
                'Accès non autorisé à ce client'
            );
        }
    }
}
```

### Phase 6: Ajouter des Scopes aux Modèles (15 min)

**Dans Client.php** :
```php
/**
 * Scope pour filtrer par utilisateur
 */
public function scopeForUser($query, int $userId)
{
    return $query->where('user_id', $userId);
}
```

**Utilisation** :
```php
// Au lieu de
$clients = Client::where('user_id', auth()->id())->get();

// On fait
$clients = Client::forUser(auth()->id())->get();
```

### Phase 7: Créer des Tests (1h)

**ClientControllerTest.php** :
```php
<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_own_clients(): void
    {
        $user = User::factory()->create();
        $clients = Client::factory()->count(3)->create([
            'user_id' => $user->id
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/clients');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_cannot_view_other_user_client(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $client = Client::factory()->create([
            'user_id' => $user1->id
        ]);

        $response = $this->actingAs($user2, 'sanctum')
            ->getJson("/api/clients/{$client->id}");

        $response->assertNotFound();
    }
}
```

## 🎯 Commandes Utiles

```bash
# Formater le code
./vendor/bin/pint

# Lancer les tests
php artisan test

# Vérifier la qualité
php artisan about

# Voir les routes
php artisan route:list

# Nettoyer les caches
php artisan optimize:clear
```

## 📊 Checklist de Vérification

- [ ] Tous les Controllers utilisent des API Resources
- [ ] Tous les Controllers utilisent des Form Requests
- [ ] Pas de logique métier dans les Controllers
- [ ] Services créés pour la logique complexe
- [ ] Scopes ajoutés aux modèles pour les requêtes fréquentes
- [ ] Eager loading partout où nécessaire
- [ ] Tests Feature pour les endpoints critiques
- [ ] Documentation PHPDoc ajoutée
- [ ] Code formaté avec Pint

## 🚀 Script Automatique

Un script `refactor-boost.sh` a été créé pour automatiser une partie du travail :

```bash
cd backend
chmod +x refactor-boost.sh
./refactor-boost.sh
```

## 📚 Ressources

- [Laravel Resources](https://laravel.com/docs/11.x/eloquent-resources)
- [Form Requests](https://laravel.com/docs/11.x/validation#form-request-validation)
- [Service Container](https://laravel.com/docs/11.x/container)
- [Testing](https://laravel.com/docs/11.x/testing)
- [Laravel Boost Guide](./GUIDE_LARAVEL_BOOST.md)

---

**Temps total estimé** : 3-4 heures

**Priorité haute** :
1. Compléter les Resources
2. Refactoriser les Controllers
3. Créer les tests de base
