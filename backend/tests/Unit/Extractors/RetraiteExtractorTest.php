<?php

namespace Tests\Unit\Extractors;

use App\Services\Ai\Extractors\RetraiteExtractor;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RetraiteExtractorTest extends TestCase {
    private RetraiteExtractor $extractor;

    protected function setUp(): void {
        parent::setUp();
        $this->extractor = new RetraiteExtractor();

        config(['mistral.features.use_for_llm' => true]);
        config(['mistral.api_key' => 'test-mistral-key']);
        config(['mistral.llm.endpoint' => 'https://api.mistral.ai/v1/chat/completions']);
        config(['mistral.fallback_to_openai' => false]);
    }

    public function test_detects_retraite_need(): void {
        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{"besoins": ["retraite"], "besoins_action": "add", "bae_retraite": {}}',
                    ],
                ]],
            ], 200),
        ]);

        $data = $this->extractor->extract('Je veux préparer ma retraite');

        $this->assertContains('retraite', $data['besoins'] ?? []);
        $this->assertEquals('add', $data['besoins_action'] ?? null);
    }

    public function test_extracts_age_depart(): void {
        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{"besoins": ["retraite"], "besoins_action": "add", "bae_retraite": {"age_depart_retraite": 62}}',
                    ],
                ]],
            ], 200),
        ]);

        $data = $this->extractor->extract('Je veux partir à la retraite à 62 ans');

        $this->assertEquals(62, $data['bae_retraite']['age_depart_retraite'] ?? null);
    }

    public function test_extracts_pourcentage_revenu(): void {
        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{"besoins": ["retraite"], "besoins_action": "add", "bae_retraite": {"pourcentage_revenu_a_maintenir": 70}}',
                    ],
                ]],
            ], 200),
        ]);

        $data = $this->extractor->extract('Je veux maintenir 70% de mes revenus à la retraite');

        $this->assertEquals(70, $data['bae_retraite']['pourcentage_revenu_a_maintenir'] ?? null);
    }

    public function test_extracts_tmi(): void {
        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{"besoins": ["retraite"], "besoins_action": "add", "bae_retraite": {"tmi": "30%"}}',
                    ],
                ]],
            ], 200),
        ]);

        $data = $this->extractor->extract('Mon TMI est de 30%');

        $this->assertEquals('30%', $data['bae_retraite']['tmi'] ?? null);
    }

    public function test_extracts_revenus_foyer(): void {
        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{"besoins": ["retraite"], "besoins_action": "add", "bae_retraite": {"revenus_annuels_foyer": 80000}}',
                    ],
                ]],
            ], 200),
        ]);

        $data = $this->extractor->extract('Le revenu foyer est de 80000 euros');

        $this->assertEquals(80000, $data['bae_retraite']['revenus_annuels_foyer'] ?? null);
    }

    public function test_extracts_contrat_existant(): void {
        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{"besoins": ["retraite"], "besoins_action": "add", "bae_retraite": {"contrat_en_place": "PER", "complementaire_retraite_mise_en_place": true}}',
                    ],
                ]],
            ], 200),
        ]);

        $data = $this->extractor->extract("J'ai déjà un PER chez Axa");

        $this->assertEquals('PER', $data['bae_retraite']['contrat_en_place'] ?? null);
        $this->assertTrue($data['bae_retraite']['complementaire_retraite_mise_en_place'] ?? false);
    }

    public function test_action_remove_when_negation(): void {
        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{"besoins": ["retraite"], "besoins_action": "remove"}',
                    ],
                ]],
            ], 200),
        ]);

        $data = $this->extractor->extract("Je n'ai plus besoin de retraite");

        $this->assertEquals('remove', $data['besoins_action'] ?? null);
    }

    public function test_returns_empty_when_no_retraite(): void {
        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{}',
                    ],
                ]],
            ], 200),
        ]);

        $data = $this->extractor->extract("Je veux garantir 3000€ en cas d'invalidité");

        $this->assertEquals([], $data);
    }

    public function test_returns_empty_on_api_error(): void {
        Http::fake([
            'api.mistral.ai/*' => Http::response(['error' => 'Server error'], 500),
        ]);

        $data = $this->extractor->extract('Je veux préparer ma retraite');

        $this->assertEquals([], $data);
    }
}
