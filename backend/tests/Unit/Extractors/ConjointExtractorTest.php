<?php

namespace Tests\Unit\Extractors;

use App\Services\Ai\Extractors\ConjointExtractor;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ConjointExtractorTest extends TestCase
{
    private ConjointExtractor $extractor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extractor = new ConjointExtractor();

        // Configuration par défaut pour les tests
        config(['mistral.features.use_for_llm' => true]);
        config(['mistral.api_key' => 'test-mistral-key']);
        config(['mistral.llm.endpoint' => 'https://api.mistral.ai/v1/chat/completions']);
        config(['mistral.llm.model' => 'mistral-small-latest']);
        config(['mistral.fallback_to_openai' => false]);
    }

    public function test_extracts_conjoint_name(): void
    {
        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{"conjoint": {"nom": "Martin", "prenom": "Sophie"}}',
                    ],
                ]],
            ], 200),
        ]);

        $data = $this->extractor->extract("Ma femme s'appelle Sophie Martin");

        $this->assertArrayHasKey('conjoint', $data);
        $this->assertEquals('Sophie', $data['conjoint']['prenom'] ?? null);
        $this->assertEquals('Martin', $data['conjoint']['nom'] ?? null);
    }

    public function test_extracts_conjoint_profession(): void
    {
        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{"conjoint": {"profession": "médecin"}}',
                    ],
                ]],
            ], 200),
        ]);

        $data = $this->extractor->extract("Mon mari est médecin");

        $this->assertEquals('médecin', $data['conjoint']['profession'] ?? null);
    }

    public function test_extracts_conjoint_birth_date(): void
    {
        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{"conjoint": {"prenom": "Sophie", "date_naissance": "1982-08-20"}}',
                    ],
                ]],
            ], 200),
        ]);

        $data = $this->extractor->extract("Ma femme Sophie est née le 20 août 1982");

        $this->assertEquals('1982-08-20', $data['conjoint']['date_naissance'] ?? null);
    }

    public function test_extracts_conjoint_complete_info(): void
    {
        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{"conjoint": {"nom": "Martin", "prenom": "Sophie", "date_naissance": "1982-08-20", "profession": "infirmière"}}',
                    ],
                ]],
            ], 200),
        ]);

        $data = $this->extractor->extract("Ma femme s'appelle Sophie Martin, elle est infirmière, née le 20 août 1982");

        $this->assertEquals('Martin', $data['conjoint']['nom'] ?? null);
        $this->assertEquals('Sophie', $data['conjoint']['prenom'] ?? null);
        $this->assertEquals('1982-08-20', $data['conjoint']['date_naissance'] ?? null);
        $this->assertEquals('infirmière', $data['conjoint']['profession'] ?? null);
    }

    public function test_returns_empty_when_no_conjoint(): void
    {
        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{}',
                    ],
                ]],
            ], 200),
        ]);

        $data = $this->extractor->extract("Je suis architecte, j'ai 45 ans");

        $this->assertEquals([], $data);
    }

    public function test_ignores_client_data(): void
    {
        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{"conjoint": {"prenom": "Sophie", "profession": "infirmière"}}',
                    ],
                ]],
            ], 200),
        ]);

        $data = $this->extractor->extract("Je m'appelle Jean Dupont, je suis architecte. Ma femme s'appelle Sophie, elle est infirmière.");

        // Ne doit pas contenir les infos du client Jean Dupont
        $this->assertArrayHasKey('conjoint', $data);
        $this->assertNotEquals('Jean', $data['conjoint']['prenom'] ?? null);
        $this->assertNotEquals('Dupont', $data['conjoint']['nom'] ?? null);
        $this->assertNotEquals('architecte', $data['conjoint']['profession'] ?? null);
        // Doit contenir les infos de Sophie
        $this->assertEquals('Sophie', $data['conjoint']['prenom'] ?? null);
        $this->assertEquals('infirmière', $data['conjoint']['profession'] ?? null);
    }

    public function test_extracts_with_mon_conjoint(): void
    {
        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{"conjoint": {"profession": "comptable"}}',
                    ],
                ]],
            ], 200),
        ]);

        $data = $this->extractor->extract("Mon conjoint est comptable");

        $this->assertEquals('comptable', $data['conjoint']['profession'] ?? null);
    }

    public function test_extracts_with_mon_epouse(): void
    {
        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{"conjoint": {"prenom": "Marie", "profession": "avocate"}}',
                    ],
                ]],
            ], 200),
        ]);

        $data = $this->extractor->extract("Mon épouse Marie est avocate");

        $this->assertEquals('Marie', $data['conjoint']['prenom'] ?? null);
        $this->assertEquals('avocate', $data['conjoint']['profession'] ?? null);
    }

    public function test_extracts_with_elle_il_reference(): void
    {
        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{"conjoint": {"profession": "professeur"}}',
                    ],
                ]],
            ], 200),
        ]);

        $data = $this->extractor->extract("Ma femme travaille, elle est professeur");

        $this->assertEquals('professeur', $data['conjoint']['profession'] ?? null);
    }

    public function test_extracts_chef_entreprise(): void
    {
        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{"conjoint": {"chef_entreprise": true, "profession": "restaurateur"}}',
                    ],
                ]],
            ], 200),
        ]);

        $data = $this->extractor->extract("Mon mari est chef d'entreprise, il a un restaurant");

        $this->assertTrue($data['conjoint']['chef_entreprise'] ?? false);
    }

    public function test_extracts_risques_professionnels(): void
    {
        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{"conjoint": {"profession": "pompier", "risques_professionnels": true}}',
                    ],
                ]],
            ], 200),
        ]);

        $data = $this->extractor->extract("Mon mari est pompier, c'est un métier à risques");

        $this->assertTrue($data['conjoint']['risques_professionnels'] ?? false);
    }

    public function test_returns_empty_on_api_error(): void
    {
        Http::fake([
            'api.mistral.ai/*' => Http::response(['error' => 'Server error'], 500),
        ]);

        $data = $this->extractor->extract("Ma femme s'appelle Sophie");

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

        $data = $this->extractor->extract("Ma femme s'appelle Sophie");

        $this->assertEquals([], $data);
    }

    public function test_ignores_children_data(): void
    {
        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{}',
                    ],
                ]],
            ], 200),
        ]);

        // Les enfants ne doivent pas être extraits comme conjoint
        $data = $this->extractor->extract("Mon fils s'appelle Pierre, il a 15 ans");

        $this->assertArrayNotHasKey('conjoint', $data);
    }

    public function test_extracts_telephone(): void
    {
        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{"conjoint": {"telephone": "0698765432"}}',
                    ],
                ]],
            ], 200),
        ]);

        $data = $this->extractor->extract("Le numéro de ma femme c'est le 06 98 76 54 32");

        $this->assertEquals('0698765432', $data['conjoint']['telephone'] ?? null);
    }
}
