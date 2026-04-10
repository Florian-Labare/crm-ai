<?php

namespace Tests\Unit\Extractors;

use App\Services\Ai\Extractors\ClientExtractor;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ClientExtractorTest extends TestCase
{
    private ClientExtractor $extractor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extractor = new ClientExtractor;

        // Configuration par défaut pour les tests
        config(['mistral.features.use_for_llm' => true]);
        config(['mistral.api_key' => 'test-mistral-key']);
        config(['mistral.llm.endpoint' => 'https://api.mistral.ai/v1/chat/completions']);
        config(['mistral.llm.model' => 'mistral-small-latest']);
        config(['mistral.fallback_to_openai' => false]);
    }

    public function test_extracts_client_name(): void
    {
        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{"nom": "Dupont", "prenom": "Jean"}',
                    ],
                ]],
            ], 200),
        ]);

        $data = $this->extractor->extract("Je m'appelle Jean Dupont, né le 15 mai 1980");

        $this->assertEquals('Dupont', $data['nom'] ?? null);
        $this->assertEquals('Jean', $data['prenom'] ?? null);
    }

    public function test_extracts_birth_date(): void
    {
        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{"nom": "Dupont", "prenom": "Jean", "date_naissance": "1980-05-15"}',
                    ],
                ]],
            ], 200),
        ]);

        $data = $this->extractor->extract("Je m'appelle Jean Dupont, né le 15 mai 1980");

        $this->assertEquals('1980-05-15', $data['date_naissance'] ?? null);
    }

    public function test_extracts_address(): void
    {
        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{"adresse": "12 rue de la Paix", "code_postal": "75001", "ville": "Paris"}',
                    ],
                ]],
            ], 200),
        ]);

        $data = $this->extractor->extract("J'habite au 12 rue de la Paix, 75001 Paris");

        $this->assertEquals('12 rue de la Paix', $data['adresse'] ?? null);
        $this->assertEquals('75001', $data['code_postal'] ?? null);
        $this->assertEquals('Paris', $data['ville'] ?? null);
    }

    public function test_extracts_profession(): void
    {
        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{"profession": "architecte", "situation_actuelle": "Salarié(e)"}',
                    ],
                ]],
            ], 200),
        ]);

        $data = $this->extractor->extract('Je suis architecte salarié');

        $this->assertEquals('architecte', $data['profession'] ?? null);
    }

    public function test_extracts_children(): void
    {
        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{"enfants": [{"prenom": "Emma"}, {"prenom": "Louis"}]}',
                    ],
                ]],
            ], 200),
        ]);

        $data = $this->extractor->extract("J'ai deux enfants, Emma et Louis");

        $this->assertIsArray($data['enfants'] ?? null);
        $this->assertCount(2, $data['enfants']);
        $this->assertEquals('Emma', $data['enfants'][0]['prenom'] ?? null);
        $this->assertEquals('Louis', $data['enfants'][1]['prenom'] ?? null);
    }

    public function test_ignores_conjoint_data(): void
    {
        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{"nom": "Dupont", "prenom": "Jean"}',
                    ],
                ]],
            ], 200),
        ]);

        $data = $this->extractor->extract("Je m'appelle Jean Dupont. Ma femme s'appelle Sophie Martin, elle est médecin.");

        $this->assertEquals('Jean', $data['prenom'] ?? null);
        $this->assertEquals('Dupont', $data['nom'] ?? null);
        // Ne doit pas contenir Sophie ou Martin
        $this->assertNotEquals('Sophie', $data['prenom'] ?? null);
        $this->assertNotEquals('Martin', $data['nom'] ?? null);
    }

    public function test_extracts_fumeur_false(): void
    {
        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{"fumeur": false}',
                    ],
                ]],
            ], 200),
        ]);

        $data = $this->extractor->extract('Je ne fume pas');

        $this->assertFalse($data['fumeur'] ?? true);
    }

    public function test_extracts_fumeur_true(): void
    {
        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{"fumeur": true}',
                    ],
                ]],
            ], 200),
        ]);

        $data = $this->extractor->extract('Je fume environ 10 cigarettes par jour');

        $this->assertTrue($data['fumeur'] ?? false);
    }

    public function test_extracts_sports_activities(): void
    {
        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{"activites_sportives": true, "details_activites_sportives": "musculation"}',
                    ],
                ]],
            ], 200),
        ]);

        $data = $this->extractor->extract('Je fais de la musculation trois fois par semaine');

        $this->assertTrue($data['activites_sportives'] ?? false);
        $this->assertEquals('musculation', $data['details_activites_sportives'] ?? null);
    }

    public function test_extracts_chef_entreprise(): void
    {
        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{"profession": "plombier", "chef_entreprise": true, "statut": "SARL"}',
                    ],
                ]],
            ], 200),
        ]);

        $data = $this->extractor->extract("Je suis plombier, gérant d'une SARL");

        $this->assertEquals('plombier', $data['profession'] ?? null);
        $this->assertTrue($data['chef_entreprise'] ?? false);
        $this->assertEquals('SARL', $data['statut'] ?? null);
    }

    public function test_extracts_email(): void
    {
        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{"email": "jean.dupont@gmail.com"}',
                    ],
                ]],
            ], 200),
        ]);

        $data = $this->extractor->extract("Mon email c'est jean point dupont arobase gmail point com");

        $this->assertEquals('jean.dupont@gmail.com', $data['email'] ?? null);
    }

    public function test_extracts_telephone(): void
    {
        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{"telephone": "0612345678"}',
                    ],
                ]],
            ], 200),
        ]);

        $data = $this->extractor->extract("Mon numéro c'est le 06 12 34 56 78");

        $this->assertEquals('0612345678', $data['telephone'] ?? null);
    }

    public function test_returns_empty_on_api_error(): void
    {
        Http::fake([
            'api.mistral.ai/*' => Http::response(['error' => 'Server error'], 500),
        ]);

        $data = $this->extractor->extract("Je m'appelle Jean Dupont");

        $this->assertEquals([], $data);
    }

    public function test_returns_empty_on_invalid_json(): void
    {
        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => 'Invalid JSON response',
                    ],
                ]],
            ], 200),
        ]);

        $data = $this->extractor->extract("Je m'appelle Jean Dupont");

        $this->assertEquals([], $data);
    }

    public function test_extracts_situation_matrimoniale(): void
    {
        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{"situation_matrimoniale": "Marié(e)"}',
                    ],
                ]],
            ], 200),
        ]);

        $data = $this->extractor->extract('Je suis marié depuis 2010');

        $this->assertEquals('Marié(e)', $data['situation_matrimoniale'] ?? null);
    }

    public function test_extracts_consentement_audio(): void
    {
        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{"consentement_audio": true}',
                    ],
                ]],
            ], 200),
        ]);

        $data = $this->extractor->extract("Oui j'accepte que vous enregistriez notre conversation");

        $this->assertTrue($data['consentement_audio'] ?? false);
    }
}
