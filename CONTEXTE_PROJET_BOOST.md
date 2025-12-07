# Contexte Projet - Laravel Boost Analysis

> 📋 Ce document a été généré en utilisant les conventions Laravel Boost pour garantir un développement cohérent et de haute qualité.

## 📊 Informations Application

### Stack Technique
- **Laravel**: v12.35.1 (Latest)
- **PHP**: v8.4.1
- **Composer**: v2.8.12
- **Environment**: Local (Development)
- **Debug Mode**: ✅ Enabled
- **Timezone**: UTC
- **Locale**: en

### Drivers Configurés

| Service | Driver |
|---------|--------|
| **Broadcasting** | log |
| **Cache** | redis ✅ |
| **Database** | mysql |
| **Logs** | single |
| **Mail** | log |
| **Octane** | swoole ✅ |
| **Queue** | redis ✅ |
| **Session** | database |

### Packages Installés

#### Production
- `laravel/framework`: ^12.0
- `laravel/sanctum`: ^4.2 (API Authentication)
- `laravel/fortify`: ^1.31 (Authentication)
- `laravel/octane`: ^2.13 (Performance Boost)
- `laravel/wayfinder`: ^0.1.9
- `spatie/laravel-permission`: ^6.23 (Roles & Permissions)
- `barryvdh/laravel-dompdf`: ^3.1 (PDF Generation)
- `phpoffice/phpword`: ^1.3 (Word Generation)

#### Development
- `laravel/boost`: ^1.8 (AI Development)
- `laravel/pail`: ^1.2 (Log Viewer)
- `laravel/pint`: ^1.18 (Code Style)

## 🗃️ Architecture Base de Données

### Modèles Eloquent Disponibles

Le projet utilise 16 modèles Eloquent principaux :

#### Gestion Clients
1. **Client** - Gestion des clients principaux
2. **Conjoint** - Informations sur les conjoints
3. **Enfant** - Gestion des enfants des clients
4. **Entreprise** - Informations entreprises (si applicable)

#### BAE (Besoin, Analyse, Épargne)
5. **BaeEpargne** - Analyse des besoins d'épargne
6. **BaePrevoyance** - Analyse des besoins de prévoyance
7. **BaeRetraite** - Analyse des besoins de retraite

#### Questionnaires Risque
8. **QuestionnaireRisque** - Questionnaire principal
9. **QuestionnaireRisqueConnaissance** - Connaissances financières
10. **QuestionnaireRisqueFinancier** - Profil financier
11. **QuestionnaireRisqueQuiz** - Quiz de compréhension

#### Documents & Médias
12. **DocumentTemplate** - Templates de documents
13. **GeneratedDocument** - Documents générés
14. **AudioRecord** - Enregistrements audio (transcription)

#### Santé & Utilisateurs
15. **SanteSouhait** - Souhaits santé/mutuelle
16. **User** - Utilisateurs de l'application

## 🛣️ Routes API Principales

### Authentication (Sanctum)
- `POST /api/login` - Connexion
- `POST /api/register` - Inscription
- `POST /api/logout` - Déconnexion
- `GET /api/user` - Utilisateur connecté

### Clients CRUD
- `GET /api/clients` - Liste des clients
- `POST /api/clients` - Créer un client
- `GET /api/clients/{id}` - Voir un client
- `PUT /api/clients/{id}` - Mettre à jour un client
- `DELETE /api/clients/{id}` - Supprimer un client

### Export Documents
- `GET /api/clients/{id}/export/pdf` - Export PDF
- `GET /api/clients/{id}/export/word` - Export Word
- `GET /api/clients/{id}/questionnaires/export/pdf` - Export questionnaire PDF

### Documents
- `GET /api/clients/{clientId}/documents` - Liste documents client
- `POST /api/clients/{clientId}/documents/generate` - Générer document
- `GET /api/documents/{documentId}/download` - Télécharger document
- `POST /api/documents/{documentId}/send-email` - Envoyer par email
- `DELETE /api/documents/{documentId}` - Supprimer document
- `GET /api/document-templates` - Liste des templates

### Audio & Transcription
- `POST /api/audio/upload` - Upload audio
- `GET /api/audio/status/{id}` - Statut transcription
- `GET /api/recordings` - Liste enregistrements
- `GET /api/recordings/{id}` - Détails enregistrement
- `DELETE /api/recordings/{id}` - Supprimer enregistrement

### Questionnaires Risque
- `GET /api/questionnaire-risque/client/{clientId}` - Questionnaire d'un client
- `POST /api/questionnaire-risque/live` - Questionnaire en live

### Utilitaires
- `GET /api/ping` - Health check
- `GET /api/test-error` - Test erreurs
- `POST /_boost/browser-logs` - Logs navigateur (Boost)

## 🏗️ Conventions Laravel Boost

### 1. Structure des Contrôleurs

✅ **Bon** (RESTful Resource Controllers):
```php
class ClientController extends Controller
{
    public function index()    // GET /clients
    public function store()    // POST /clients
    public function show($id)  // GET /clients/{id}
    public function update($id) // PUT /clients/{id}
    public function destroy($id) // DELETE /clients/{id}
}
```

### 2. Validation avec Form Requests

✅ **Bon**:
```php
// app/Http/Requests/StoreClientRequest.php
class StoreClientRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'nom' => 'required|string|max:255',
            'email' => 'required|email|unique:clients',
        ];
    }
}

// Dans le contrôleur
public function store(StoreClientRequest $request)
{
    // Données déjà validées
    $client = Client::create($request->validated());
}
```

### 3. Modèles Eloquent

✅ **Bon**:
```php
class Client extends Model
{
    // Mass assignment protection
    protected $fillable = ['nom', 'prenom', 'email'];

    // Ou inversement
    protected $guarded = ['id'];

    // Type casting
    protected $casts = [
        'date_naissance' => 'date',
        'consentement_audio' => 'boolean',
        'revenus_annuels' => 'decimal:2',
    ];

    // Relations
    public function enfants()
    {
        return $this->hasMany(Enfant::class);
    }

    public function conjoint()
    {
        return $this->hasOne(Conjoint::class);
    }
}
```

### 4. Services Layer

✅ **Bon** (Logic dans Services):
```php
// app/Services/ClientSyncService.php
class ClientSyncService
{
    public function syncClientData(Client $client, array $data): void
    {
        // Business logic ici
        DB::transaction(function () use ($client, $data) {
            $client->update($data['client']);
            $this->syncEnfants($client, $data['enfants']);
        });
    }
}

// Utilisation dans le contrôleur
public function update(UpdateClientRequest $request, ClientSyncService $service)
{
    $service->syncClientData($client, $request->validated());
    return response()->json($client);
}
```

### 5. Jobs Asynchrones

✅ **Bon**:
```php
// app/Jobs/ProcessAudioRecording.php
class ProcessAudioRecording implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public AudioRecord $audioRecord
    ) {}

    public function handle(TranscriptionService $service): void
    {
        $service->transcribe($this->audioRecord);
    }
}

// Dispatch
ProcessAudioRecording::dispatch($audioRecord);
```

### 6. API Resources

✅ **Bon**:
```php
// app/Http/Resources/ClientResource.php
class ClientResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'prenom' => $this->prenom,
            'email' => $this->email,
            'created_at' => $this->created_at,
            'enfants' => EnfantResource::collection($this->whenLoaded('enfants')),
        ];
    }
}

// Utilisation
return ClientResource::collection($clients);
```

### 7. Testing

✅ **Bon**:
```php
// tests/Feature/ClientControllerTest.php
class ClientControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_client(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/clients', [
                'nom' => 'Dupont',
                'prenom' => 'Jean',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['id', 'nom', 'prenom']);
    }
}
```

## 🎯 Best Practices Actuelles du Projet

### ✅ Ce qui est bien fait

1. **Architecture en couches**
   - Controllers minces
   - Services pour la logique métier
   - Form Requests pour validation

2. **Jobs asynchrones**
   - Transcription audio via ProcessAudioRecording
   - Queue Redis configurée

3. **Sécurité**
   - Sanctum pour l'API
   - Middleware d'authentification
   - Permissions avec Spatie

4. **Performance**
   - Octane avec Swoole installé
   - Redis pour cache et queues
   - Eager loading des relations

5. **Documentation**
   - Export PDF/Word configuré
   - Templates de documents

## 🚀 Recommandations Boost

### Pour le Développement Futur

1. **API Resources**
   - Créer des Resources pour formater les réponses JSON
   - Éviter de retourner directement les modèles

2. **Tests**
   - Ajouter des Feature tests pour les endpoints critiques
   - Tests unitaires pour les Services

3. **Logs**
   - Utiliser des channels de logs spécifiques
   - Structurer les logs pour faciliter le debugging

4. **Cache**
   - Utiliser le cache Redis pour les requêtes fréquentes
   - Cache tags pour invalidation fine

5. **Rate Limiting**
   - Ajouter du rate limiting sur les endpoints publics
   - Protéger contre les abus

## 📝 Conventions de Nommage

### Base de Données
- Tables: `snake_case` pluriel (`clients`, `audio_records`)
- Colonnes: `snake_case` (`date_naissance`, `revenus_annuels`)
- Clés étrangères: `{model}_id` (`client_id`, `user_id`)

### PHP
- Classes: `PascalCase` (`ClientController`, `ProcessAudioRecording`)
- Méthodes: `camelCase` (`syncClientData`, `processTranscription`)
- Variables: `camelCase` (`$audioRecord`, `$clientData`)

### Routes
- API: Préfixées par `/api`
- RESTful: Utiliser les verbes HTTP standards
- Nommage: `{resource}.{action}` optionnel

## 🎓 Conclusion

Ce projet suit les conventions Laravel modernes avec :
- ✅ Laravel 12.35.1 (latest)
- ✅ Architecture RESTful propre
- ✅ Services Layer bien structuré
- ✅ Jobs asynchrones configurés
- ✅ Octane pour la performance
- ✅ Boost pour le développement IA

**Continuez à utiliser ces patterns pour maintenir la qualité et la cohérence du code !** 🚀
