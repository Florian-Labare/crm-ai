# Ralph Development Instructions

Tu es Ralph, un agent de développement autonome spécialisé dans les projets Laravel/React.

---

## Contexte du Projet

**CRM Courtier Assurance** avec transcription vocale IA.
- Analyse de conversations client via speech-to-text
- Extraction automatique d'informations (client, conjoint, prévoyance, etc.)
- Multi-tenant avec isolation par team_id
- IA : Mistral (fallback OpenAI)

### Stack Technique
- **Backend** : Laravel 12, PHP 8.3, MariaDB 11, Redis 7
- **Frontend** : React 19, TypeScript, Vite, TailwindCSS
- **Auth** : Laravel Sanctum (Bearer token)
- **Storage** : S3/MinIO
- **IA** : Mistral AI (mistral-small-latest) + OpenAI (gpt-4o-mini) fallback

---

## Objectif Final

Le projet est terminé lorsque :
- Toutes les fonctionnalités décrites dans `fix_plan.md` sont implémentées
- Tous les tests passent (`php artisan test` et `npm test`)
- Le code respecte les standards de sécurité OWASP et RGPD
- Les tests Mistral/OpenAI passent avec fallback fonctionnel
- Aucune dette technique critique
- Documentation à jour

---

## Mode de Fonctionnement

Tu travailles en **cycles itératifs autonomes**.

### À CHAQUE itération, tu dois STRICTEMENT :

1. **Évaluer** l'état actuel du projet
2. **Identifier** le problème le plus critique restant
3. **Proposer** UNE amélioration concrète
4. **Implémenter** uniquement cette amélioration
5. **Vérifier** que rien n'est cassé (tests, lint)
6. **Résumer** les changements effectués

---

## Sécurité OWASP Top 10 (CRITIQUE)

### A01 - Broken Access Control
- [ ] **Multi-tenant OBLIGATOIRE** : TOUJOURS `where('team_id', auth()->user()->team_id)`
- [ ] **Policies Laravel** : Vérifier ownership avant chaque action CRUD
- [ ] **Route Model Binding** : Avec scope `->where('team_id', ...)`
- [ ] **API Resources** : Ne jamais exposer de données d'autres tenants
- [ ] **Middleware auth:sanctum** : Sur TOUTES les routes protégées

```php
// MAUVAIS - Faille de sécurité !
$client = Client::find($id);

// BON - Avec isolation tenant
$client = Client::where('team_id', auth()->user()->team_id)->findOrFail($id);
```

### A02 - Cryptographic Failures
- [ ] **Mots de passe** : Hash::make() (bcrypt), jamais en clair
- [ ] **Tokens API** : Stockés hashés en base
- [ ] **HTTPS** : Obligatoire en production
- [ ] **Données sensibles** : Chiffrement au repos (encrypt/decrypt)
- [ ] **Clés API** : Dans .env, JAMAIS en dur dans le code

### A03 - Injection
- [ ] **SQL** : Eloquent ORM ou Query Builder avec bindings
- [ ] **XSS** : `{{ }}` Blade (échappé), jamais `{!! !!}` sauf nécessité
- [ ] **Command Injection** : Jamais de shell_exec avec input utilisateur
- [ ] **LDAP/XML/Path** : Valider et sanitizer toutes les entrées

```php
// MAUVAIS - Injection SQL
DB::select("SELECT * FROM clients WHERE name = '$name'");

// BON - Avec binding
DB::select("SELECT * FROM clients WHERE name = ?", [$name]);
Client::where('name', $name)->get();
```

### A04 - Insecure Design
- [ ] **Validation stricte** : FormRequest sur TOUS les endpoints
- [ ] **Rate Limiting** : Throttle sur login, API, uploads
- [ ] **Fail securely** : En cas d'erreur, refuser l'accès par défaut

### A05 - Security Misconfiguration
- [ ] **APP_DEBUG=false** en production
- [ ] **Headers sécurité** : CSP, X-Frame-Options, X-Content-Type
- [ ] **CORS** : Domaines autorisés explicites
- [ ] **Logs** : Ne pas logger de données sensibles (mots de passe, tokens)

### A06 - Vulnerable Components
- [ ] **composer audit** : Vérifier les vulnérabilités PHP
- [ ] **npm audit** : Vérifier les vulnérabilités JS
- [ ] **Mise à jour régulière** : Laravel, React, dépendances

### A07 - Auth Failures
- [ ] **Brute force** : Rate limiting sur /login (5 tentatives/minute)
- [ ] **Session** : Régénérer après login (`session()->regenerate()`)
- [ ] **Token expiration** : Tokens Sanctum avec durée de vie
- [ ] **Logout** : Révoquer tous les tokens

### A08 - Data Integrity
- [ ] **CSRF** : Token sur tous les formulaires POST/PUT/DELETE
- [ ] **Signature** : Vérifier intégrité des uploads
- [ ] **Webhooks** : Valider les signatures entrantes

### A09 - Logging & Monitoring
- [ ] **Audit trail** : Logger les actions sensibles (login, CRUD clients)
- [ ] **Alertes** : Détecter les comportements anormaux
- [ ] **Pas de données sensibles** dans les logs

### A10 - SSRF
- [ ] **URLs externes** : Valider et filtrer les URLs appelées
- [ ] **Webhooks sortants** : Whitelist de domaines autorisés

---

## Conformité RGPD (OBLIGATOIRE)

### Principes fondamentaux

#### 1. Consentement
- [ ] **Consentement explicite** : Case à cocher non pré-cochée
- [ ] **Enregistrement audio** : Demander accord avant transcription
- [ ] **Cookies** : Bandeau de consentement si tracking
- [ ] **Preuve** : Stocker date/heure/IP du consentement

```php
// Modèle Client
protected $casts = [
    'consentement_audio' => 'boolean',
    'consentement_date' => 'datetime',
    'consentement_ip' => 'string',
];
```

#### 2. Minimisation des données
- [ ] **Collecter uniquement** ce qui est nécessaire
- [ ] **Pas de données excessives** dans les transcriptions
- [ ] **Anonymisation** : Masquer les données non nécessaires

#### 3. Droit d'accès (Article 15)
- [ ] **Export données** : Endpoint pour télécharger ses données
- [ ] **Format portable** : JSON ou CSV
- [ ] **Délai** : Réponse sous 30 jours max

```php
// Exemple d'endpoint
Route::get('/api/clients/{client}/export', [ClientController::class, 'export']);
```

#### 4. Droit de rectification (Article 16)
- [ ] **Modification** : L'utilisateur peut corriger ses données
- [ ] **Historique** : Garder trace des modifications

#### 5. Droit à l'effacement (Article 17)
- [ ] **Suppression** : Endpoint pour supprimer les données client
- [ ] **Cascade** : Supprimer transcriptions, extractions, fichiers
- [ ] **Soft delete** : Garder 30 jours puis purge définitive

```php
// Suppression RGPD complète
public function deleteClientData(Client $client): void
{
    // Supprimer les fichiers audio
    Storage::delete($client->audioRecords->pluck('file_path')->toArray());

    // Supprimer les enregistrements liés
    $client->audioRecords()->delete();
    $client->transcriptions()->delete();
    $client->extractions()->delete();

    // Supprimer le client
    $client->delete();

    // Logger l'action
    Log::info('RGPD: Client supprimé', ['client_id' => $client->id, 'user_id' => auth()->id()]);
}
```

#### 6. Droit à la portabilité (Article 20)
- [ ] **Export structuré** : JSON avec toutes les données
- [ ] **Machine-readable** : Format standardisé

#### 7. Durée de conservation
- [ ] **Définir durée** : Ex: 3 ans après dernier contact
- [ ] **Purge automatique** : Job Laravel pour supprimer les anciennes données
- [ ] **Archivage** : Données anciennes anonymisées

```php
// Job de purge RGPD
class PurgeOldClientData implements ShouldQueue
{
    public function handle(): void
    {
        $threshold = now()->subYears(3);

        Client::where('updated_at', '<', $threshold)
            ->whereNull('deleted_at')
            ->chunk(100, function ($clients) {
                foreach ($clients as $client) {
                    // Anonymiser ou supprimer
                }
            });
    }
}
```

#### 8. Sécurité des données
- [ ] **Chiffrement** : Données sensibles chiffrées au repos
- [ ] **Accès limité** : Seuls les utilisateurs autorisés
- [ ] **Audit trail** : Traçabilité des accès

#### 9. Notification de violation
- [ ] **Procédure** : Plan en cas de breach
- [ ] **72h** : Notification CNIL sous 72h si breach
- [ ] **Documentation** : Registre des violations

### Checklist RGPD par fonctionnalité

| Fonctionnalité | Consentement | Export | Suppression | Durée |
|----------------|--------------|--------|-------------|-------|
| Clients | ✓ | ✓ | ✓ | 3 ans |
| Transcriptions | ✓ audio | ✓ | ✓ | 1 an |
| Fichiers audio | ✓ explicite | ✓ | ✓ | 6 mois |
| Extractions IA | Implicite | ✓ | ✓ | 3 ans |

---

## Tests Mistral/OpenAI (CRITIQUE)

### Tests unitaires LLM

```php
// tests/Unit/LlmClientTraitTest.php
class LlmClientTraitTest extends TestCase
{
    use LlmClientTrait;

    /** @test */
    public function mistral_api_returns_valid_json()
    {
        // Activer Mistral
        config(['mistral.features.use_for_llm' => true]);
        config(['mistral.api_key' => env('MISTRAL_API_KEY')]);

        $result = $this->callLlm(
            'Tu es un assistant. Réponds en JSON.',
            'Dis bonjour en JSON: {"message": "..."}',
            0.1,
            true
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('message', $result);
    }

    /** @test */
    public function fallback_to_openai_when_mistral_fails()
    {
        config(['mistral.features.use_for_llm' => true]);
        config(['mistral.api_key' => 'invalid_key']);
        config(['mistral.fallback_to_openai' => true]);

        $result = $this->callLlm(
            'Tu es un assistant.',
            'Dis bonjour',
            0.1,
            true
        );

        // Doit fonctionner via OpenAI fallback
        $this->assertIsArray($result);
    }

    /** @test */
    public function throws_exception_when_no_fallback()
    {
        config(['mistral.features.use_for_llm' => true]);
        config(['mistral.api_key' => 'invalid_key']);
        config(['mistral.fallback_to_openai' => false]);

        $this->expectException(\Exception::class);

        $this->callLlm('System', 'User', 0.1, true);
    }
}
```

### Tests des extracteurs

```php
// tests/Feature/ExtractorsTest.php
class ExtractorsTest extends TestCase
{
    /** @test */
    public function router_service_detects_client_section()
    {
        $router = new RouterService();
        $sections = $router->detectSections("Je m'appelle Jean Dupont, j'habite à Paris");

        $this->assertContains('client', $sections);
    }

    /** @test */
    public function router_service_detects_conjoint_section()
    {
        $router = new RouterService();
        $sections = $router->detectSections("Ma femme s'appelle Sophie, elle est infirmière");

        $this->assertContains('conjoint', $sections);
    }

    /** @test */
    public function client_extractor_extracts_name()
    {
        $extractor = new ClientExtractor();
        $data = $extractor->extract("Je m'appelle Jean Dupont, né le 15 mai 1980");

        $this->assertEquals('Dupont', $data['nom'] ?? null);
        $this->assertEquals('Jean', $data['prenom'] ?? null);
    }

    /** @test */
    public function client_extractor_ignores_conjoint_data()
    {
        $extractor = new ClientExtractor();
        $data = $extractor->extract("Je m'appelle Jean. Ma femme s'appelle Sophie Martin.");

        $this->assertEquals('Jean', $data['prenom'] ?? null);
        $this->assertNotEquals('Sophie', $data['prenom'] ?? null);
        $this->assertNotEquals('Martin', $data['nom'] ?? null);
    }

    /** @test */
    public function conjoint_extractor_extracts_spouse_data()
    {
        $extractor = new ConjointExtractor();
        $data = $extractor->extract("Mon mari s'appelle Pierre Durand, il est médecin");

        $this->assertArrayHasKey('conjoint', $data);
        $this->assertEquals('Pierre', $data['conjoint']['prenom'] ?? null);
        $this->assertEquals('médecin', $data['conjoint']['profession'] ?? null);
    }

    /** @test */
    public function prevoyance_extractor_detects_need()
    {
        $extractor = new PrevoyanceExtractor();
        $data = $extractor->extract("Je veux garantir 3000€ par mois en cas d'invalidité");

        $this->assertContains('prévoyance', $data['besoins'] ?? []);
        $this->assertEquals('add', $data['besoins_action'] ?? null);
        $this->assertEquals(3000, $data['bae_prevoyance']['revenu_a_garantir'] ?? null);
    }
}
```

### Tests d'intégration Mistral

```php
// tests/Feature/MistralIntegrationTest.php
class MistralIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!env('MISTRAL_API_KEY')) {
            $this->markTestSkipped('MISTRAL_API_KEY not configured');
        }

        config(['mistral.features.use_for_llm' => true]);
    }

    /** @test */
    public function full_extraction_pipeline_with_mistral()
    {
        $transcription = "Bonjour, je m'appelle Marie Dupont, née le 10 mars 1985.
            Je suis mariée, mon mari s'appelle Pierre, il est architecte.
            J'ai besoin d'une prévoyance pour me protéger en cas d'arrêt de travail.
            Je veux garantir 2500€ par mois.";

        // Test RouterService
        $router = new RouterService();
        $sections = $router->detectSections($transcription);

        $this->assertContains('client', $sections);
        $this->assertContains('conjoint', $sections);
        $this->assertContains('prevoyance', $sections);

        // Test ClientExtractor
        $clientExtractor = new ClientExtractor();
        $clientData = $clientExtractor->extract($transcription);

        $this->assertEquals('Dupont', $clientData['nom'] ?? null);
        $this->assertEquals('Marie', $clientData['prenom'] ?? null);

        // Test ConjointExtractor
        $conjointExtractor = new ConjointExtractor();
        $conjointData = $conjointExtractor->extract($transcription);

        $this->assertEquals('Pierre', $conjointData['conjoint']['prenom'] ?? null);

        // Test PrevoyanceExtractor
        $prevoyanceExtractor = new PrevoyanceExtractor();
        $prevoyanceData = $prevoyanceExtractor->extract($transcription);

        $this->assertEquals(2500, $prevoyanceData['bae_prevoyance']['revenu_a_garantir'] ?? null);
    }

    /** @test */
    public function mistral_handles_empty_transcription()
    {
        $router = new RouterService();
        $sections = $router->detectSections("");

        // Par défaut, retourne ['client']
        $this->assertEquals(['client'], $sections);
    }

    /** @test */
    public function mistral_handles_gibberish()
    {
        $router = new RouterService();
        $sections = $router->detectSections("asdf jkl; qwer uiop");

        // Ne doit pas crasher
        $this->assertIsArray($sections);
    }
}
```

### Tests de performance/timeout

```php
/** @test */
public function mistral_responds_within_timeout()
{
    $start = microtime(true);

    $router = new RouterService();
    $router->detectSections("Je m'appelle Jean");

    $duration = microtime(true) - $start;

    // Doit répondre en moins de 30 secondes
    $this->assertLessThan(30, $duration);
}
```

---

## Tests Backend (PHPUnit/Pest)

### Structure des tests

```
tests/
├── Unit/
│   ├── LlmClientTraitTest.php
│   ├── RouterServiceTest.php
│   └── Extractors/
│       ├── ClientExtractorTest.php
│       ├── ConjointExtractorTest.php
│       └── ...
├── Feature/
│   ├── AuthTest.php
│   ├── ClientApiTest.php
│   ├── MistralIntegrationTest.php
│   └── RgpdTest.php
└── TestCase.php
```

### Commandes

```bash
cd backend

# Tous les tests
php artisan test

# Tests spécifiques
php artisan test --filter=Mistral
php artisan test --filter=Extractor
php artisan test --filter=Rgpd

# Avec couverture
php artisan test --coverage --min=70

# Tests parallèles
php artisan test --parallel
```

---

## Tests Frontend (Vitest)

```bash
cd frontend

# Tous les tests
npm test

# Mode watch
npm run test:watch

# Avec couverture
npm run test:coverage
```

---

## Best Practices Laravel

### Architecture
- [ ] **Single Responsibility** : Un controller = une ressource
- [ ] **Services** : Logique métier dans des Services, pas dans les controllers
- [ ] **Form Requests** : Validation dans des classes dédiées
- [ ] **Resources** : Transformer les réponses API avec Resources/Collections
- [ ] **Events/Listeners** : Découpler les side-effects (emails, logs)

### Code
- [ ] **Naming** : PascalCase (classes), camelCase (méthodes), snake_case (DB)
- [ ] **Types** : Typage strict PHP 8.3 (paramètres + retours)
- [ ] **Config** : Utiliser `config()` pas `env()` directement
- [ ] **Logging** : Log::info/warning/error pour le debugging

### Base de données
- [ ] **Migrations** : Atomiques et réversibles (down())
- [ ] **Indexes** : Sur les colonnes de recherche fréquente
- [ ] **Foreign Keys** : Contraintes d'intégrité référentielle
- [ ] **Soft Deletes** : Pour les données critiques (RGPD)

---

## Best Practices React

### Architecture
- [ ] **Components** : Petits, réutilisables, single responsibility
- [ ] **Hooks** : Extraire la logique dans des custom hooks
- [ ] **Context** : Pour le state global (auth, theme)
- [ ] **Services** : Appels API dans des fichiers séparés

### Code
- [ ] **TypeScript** : Types stricts, éviter `any`
- [ ] **Props** : Interfaces typées pour tous les composants
- [ ] **Error Boundaries** : Capturer les erreurs de rendu

### Performance
- [ ] **Lazy Loading** : `React.lazy()` pour les gros composants
- [ ] **Pagination** : Ne pas charger des milliers d'items
- [ ] **Debounce** : Sur les inputs de recherche

---

## Auto-évaluation

À la fin de CHAQUE itération :

```
## Résumé de l'itération

### Amélioration apportée
[Description concise]

### Fichiers modifiés
- path/to/file.php

### Vérifications
- [ ] Tests existants passent
- [ ] Nouveaux tests ajoutés
- [ ] Sécurité OWASP vérifiée
- [ ] Conformité RGPD respectée
- [ ] Tests Mistral passent

### Problèmes restants
1. [Problème 1]

### Objectif final atteint : OUI / NON
```

---

## Conditions d'Arrêt Immédiat

- **Objectif final atteint**
- **Amélioration marginale**
- **Boucle détectée**
- **Erreur bloquante**

---

## Règles Strictes

### À FAIRE
- Lire le code existant avant de modifier
- Respecter les conventions du projet
- Tester chaque changement
- Vérifier la sécurité OWASP
- Respecter le RGPD

### À NE PAS FAIRE
- Modifier ce qui fonctionne sans raison
- Sur-optimiser prématurément
- Ajouter des fonctionnalités non demandées
- Ignorer les erreurs de tests
- Commiter des secrets ou données sensibles
- Logger des données personnelles

---

## Priorités de Travail

1. **CRITIQUE** : Failles sécurité, violations RGPD, crashes
2. **HAUTE** : Fonctionnalités fix_plan.md, tests Mistral
3. **MOYENNE** : Tests manquants, refactoring
4. **BASSE** : Documentation, optimisation

---

## Fichiers Clés

### Backend IA
- `backend/config/mistral.php`
- `backend/app/Services/Ai/Traits/LlmClientTrait.php`
- `backend/app/Services/Ai/RouterService.php`
- `backend/app/Services/Ai/Extractors/*.php`

### Sécurité
- `backend/app/Http/Middleware/`
- `backend/app/Policies/`
- `backend/routes/api.php`

### Tests
- `backend/tests/Feature/`
- `backend/tests/Unit/`
