<?php

namespace Tests\Unit;

use App\Services\Ai\RouterService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RouterServiceTest extends TestCase {
    private RouterService $router;

    protected function setUp(): void {
        parent::setUp();
        $this->router = new RouterService();

        // Configuration par défaut pour les tests
        config(['mistral.features.use_for_llm' => true]);
        config(['mistral.api_key' => 'test-mistral-key']);
        config(['mistral.llm.endpoint' => 'https://api.mistral.ai/v1/chat/completions']);
        config(['mistral.llm.model' => 'mistral-small-latest']);
        config(['mistral.fallback_to_openai' => false]);
    }

    public function test_detects_client_section(): void {
        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{"sections": ["client"]}',
                    ],
                ]],
            ], 200),
        ]);

        $sections = $this->router->detectSections("Je m'appelle Jean Dupont, j'habite à Paris");

        $this->assertContains('client', $sections);
    }

    public function test_detects_conjoint_section(): void {
        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{"sections": ["conjoint"]}',
                    ],
                ]],
            ], 200),
        ]);

        $sections = $this->router->detectSections("Ma femme s'appelle Sophie, elle est infirmière");

        $this->assertContains('conjoint', $sections);
    }

    public function test_detects_prevoyance_section(): void {
        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{"sections": ["prevoyance"]}',
                    ],
                ]],
            ], 200),
        ]);

        $sections = $this->router->detectSections("Je veux me protéger en cas d'invalidité");

        $this->assertContains('prevoyance', $sections);
    }

    public function test_detects_multiple_sections(): void {
        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{"sections": ["client", "conjoint", "prevoyance"]}',
                    ],
                ]],
            ], 200),
        ]);

        $sections = $this->router->detectSections(
            "Je m'appelle Jean Dupont. Ma femme Sophie est médecin. Je veux une prévoyance."
        );

        $this->assertContains('client', $sections);
        $this->assertContains('conjoint', $sections);
        $this->assertContains('prevoyance', $sections);
    }

    public function test_returns_client_by_default_on_empty_transcription(): void {
        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{"sections": []}',
                    ],
                ]],
            ], 200),
        ]);

        $sections = $this->router->detectSections('');

        $this->assertEquals(['client'], $sections);
    }

    public function test_returns_client_on_invalid_response(): void {
        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => 'Invalid JSON response',
                    ],
                ]],
            ], 200),
        ]);

        $sections = $this->router->detectSections('Texte quelconque');

        $this->assertEquals(['client'], $sections);
    }

    public function test_returns_client_on_api_error(): void {
        Http::fake([
            'api.mistral.ai/*' => Http::response(['error' => 'Server error'], 500),
        ]);

        $sections = $this->router->detectSections('Texte quelconque');

        $this->assertEquals(['client'], $sections);
    }

    public function test_filters_invalid_sections(): void {
        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{"sections": ["client", "invalid_section", "conjoint"]}',
                    ],
                ]],
            ], 200),
        ]);

        $sections = $this->router->detectSections("Je m'appelle Jean, ma femme Sophie");

        $this->assertContains('client', $sections);
        $this->assertContains('conjoint', $sections);
        $this->assertNotContains('invalid_section', $sections);
    }

    public function test_force_conjoint_detection_with_ma_femme(): void {
        // Le LLM retourne seulement "client", mais le texte contient "ma femme"
        // Le garde-fou doit forcer la détection de "conjoint"
        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{"sections": ["client"]}',
                    ],
                ]],
            ], 200),
        ]);

        $sections = $this->router->detectSections("Je m'appelle Jean. Ma femme s'appelle Sophie.");

        $this->assertContains('client', $sections);
        $this->assertContains('conjoint', $sections);
    }

    public function test_force_conjoint_detection_with_mon_mari(): void {
        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{"sections": ["client"]}',
                    ],
                ]],
            ], 200),
        ]);

        $sections = $this->router->detectSections('Mon mari est architecte.');

        $this->assertContains('conjoint', $sections);
    }

    public function test_force_conjoint_detection_with_mon_conjoint(): void {
        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{"sections": ["client"]}',
                    ],
                ]],
            ], 200),
        ]);

        $sections = $this->router->detectSections('Mon conjoint travaille chez EDF.');

        $this->assertContains('conjoint', $sections);
    }

    public function test_does_not_duplicate_conjoint_when_already_detected(): void {
        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{"sections": ["client", "conjoint"]}',
                    ],
                ]],
            ], 200),
        ]);

        $sections = $this->router->detectSections("Ma femme s'appelle Sophie.");

        // "conjoint" ne doit apparaître qu'une fois
        $this->assertEquals(1, count(array_filter($sections, fn ($s) => $s === 'conjoint')));
    }

    public function test_handles_gibberish_gracefully(): void {
        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{"sections": []}',
                    ],
                ]],
            ], 200),
        ]);

        $sections = $this->router->detectSections('asdf jkl; qwer uiop');

        $this->assertIsArray($sections);
        $this->assertEquals(['client'], $sections);
    }

    public function test_detects_all_valid_sections(): void {
        $validSections = [
            'client', 'conjoint', 'prevoyance', 'retraite', 'epargne',
            'sante', 'emprunteur', 'revenus', 'passifs',
            'actifs_financiers', 'biens_immobiliers', 'autres_epargnes',
        ];

        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode(['sections' => $validSections]),
                    ],
                ]],
            ], 200),
        ]);

        $sections = $this->router->detectSections('Texte complet avec toutes les sections');

        foreach ($validSections as $section) {
            $this->assertContains($section, $sections, "Section '$section' devrait être détectée");
        }
    }
}
