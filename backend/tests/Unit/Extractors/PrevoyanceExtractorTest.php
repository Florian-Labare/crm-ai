<?php

namespace Tests\Unit\Extractors;

use App\Services\Ai\Extractors\PrevoyanceExtractor;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PrevoyanceExtractorTest extends TestCase {
    private PrevoyanceExtractor $extractor;

    protected function setUp(): void {
        parent::setUp();
        $this->extractor = new PrevoyanceExtractor();

        config(['mistral.features.use_for_llm' => true]);
        config(['mistral.api_key' => 'test-mistral-key']);
        config(['mistral.llm.endpoint' => 'https://api.mistral.ai/v1/chat/completions']);
        config(['mistral.fallback_to_openai' => false]);
    }

    public function test_detects_prevoyance_need(): void {
        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{"besoins": ["prévoyance"], "besoins_action": "add", "bae_prevoyance": {}}',
                    ],
                ]],
            ], 200),
        ]);

        $data = $this->extractor->extract("J'ai besoin d'une prévoyance");

        $this->assertContains('prévoyance', $data['besoins'] ?? []);
        $this->assertEquals('add', $data['besoins_action'] ?? null);
    }

    public function test_extracts_revenu_a_garantir(): void {
        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{"besoins": ["prévoyance"], "besoins_action": "add", "bae_prevoyance": {"souhaite_couverture_invalidite": true, "revenu_a_garantir": 3000}}',
                    ],
                ]],
            ], 200),
        ]);

        $data = $this->extractor->extract("Je veux garantir 3000€ par mois en cas d'invalidité");

        $this->assertEquals(3000, $data['bae_prevoyance']['revenu_a_garantir'] ?? null);
        $this->assertTrue($data['bae_prevoyance']['souhaite_couverture_invalidite'] ?? false);
    }

    public function test_extracts_capital_deces(): void {
        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{"besoins": ["prévoyance"], "besoins_action": "add", "bae_prevoyance": {"capital_deces_souhaite": 200000}}',
                    ],
                ]],
            ], 200),
        ]);

        $data = $this->extractor->extract('Je veux un capital décès de 200000€');

        $this->assertEquals(200000, $data['bae_prevoyance']['capital_deces_souhaite'] ?? null);
    }

    public function test_extracts_rente_conjoint(): void {
        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{"besoins": ["prévoyance"], "besoins_action": "add", "bae_prevoyance": {"rente_conjoint": 1500}}',
                    ],
                ]],
            ], 200),
        ]);

        $data = $this->extractor->extract('Je veux une rente de 1500€ pour ma femme en cas de décès');

        $this->assertEquals(1500, $data['bae_prevoyance']['rente_conjoint'] ?? null);
    }

    public function test_extracts_charges_professionnelles(): void {
        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{"besoins": ["prévoyance"], "besoins_action": "add", "bae_prevoyance": {"souhaite_couvrir_charges_professionnelles": true, "montant_annuel_charges_professionnelles": 50000}}',
                    ],
                ]],
            ], 200),
        ]);

        $data = $this->extractor->extract('Je veux couvrir mes charges professionnelles de 50000€ par an');

        $this->assertTrue($data['bae_prevoyance']['souhaite_couvrir_charges_professionnelles'] ?? false);
        $this->assertEquals(50000, $data['bae_prevoyance']['montant_annuel_charges_professionnelles'] ?? null);
    }

    public function test_action_remove_when_negation(): void {
        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{"besoins": ["prévoyance"], "besoins_action": "remove"}',
                    ],
                ]],
            ], 200),
        ]);

        $data = $this->extractor->extract("Je n'ai plus besoin de prévoyance");

        $this->assertEquals('remove', $data['besoins_action'] ?? null);
    }

    public function test_returns_empty_when_no_prevoyance(): void {
        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{}',
                    ],
                ]],
            ], 200),
        ]);

        $data = $this->extractor->extract('Je veux préparer ma retraite');

        $this->assertEquals([], $data);
    }

    public function test_extracts_duree_indemnisation(): void {
        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{"besoins": ["prévoyance"], "besoins_action": "add", "bae_prevoyance": {"duree_indemnisation_souhaitee": "jusqu\'à la retraite"}}',
                    ],
                ]],
            ], 200),
        ]);

        $data = $this->extractor->extract("Je veux être indemnisé jusqu'à la retraite");

        $this->assertEquals("jusqu'à la retraite", $data['bae_prevoyance']['duree_indemnisation_souhaitee'] ?? null);
    }

    public function test_returns_empty_on_api_error(): void {
        Http::fake([
            'api.mistral.ai/*' => Http::response(['error' => 'Server error'], 500),
        ]);

        $data = $this->extractor->extract("J'ai besoin d'une prévoyance");

        $this->assertEquals([], $data);
    }
}
