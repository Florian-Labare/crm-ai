<?php

namespace Tests\Unit\Extractors;

use App\Services\Ai\Extractors\EpargneExtractor;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EpargneExtractorTest extends TestCase
{
    private EpargneExtractor $extractor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extractor = new EpargneExtractor();

        config(['mistral.features.use_for_llm' => true]);
        config(['mistral.api_key' => 'test-mistral-key']);
        config(['mistral.llm.endpoint' => 'https://api.mistral.ai/v1/chat/completions']);
        config(['mistral.fallback_to_openai' => false]);
    }

    public function test_detects_epargne_need(): void
    {
        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{"besoins": ["épargne"], "besoins_action": "add", "bae_epargne": {}}',
                    ],
                ]],
            ], 200),
        ]);

        $data = $this->extractor->extract("Je veux optimiser mon patrimoine");

        $this->assertContains('épargne', $data['besoins'] ?? []);
        $this->assertEquals('add', $data['besoins_action'] ?? null);
    }

    public function test_extracts_montant_epargne(): void
    {
        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{"besoins": ["épargne"], "besoins_action": "add", "bae_epargne": {"epargne_disponible": true, "montant_epargne_disponible": 50000}}',
                    ],
                ]],
            ], 200),
        ]);

        $data = $this->extractor->extract("J'ai 50000€ d'épargne disponible");

        $this->assertTrue($data['bae_epargne']['epargne_disponible'] ?? false);
        $this->assertEquals(50000, $data['bae_epargne']['montant_epargne_disponible'] ?? null);
    }

    public function test_extracts_capacite_epargne(): void
    {
        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{"besoins": ["épargne"], "besoins_action": "add", "bae_epargne": {"capacite_epargne_estimee": 500}}',
                    ],
                ]],
            ], 200),
        ]);

        $data = $this->extractor->extract("Je peux épargner 500€ par mois");

        $this->assertEquals(500, $data['bae_epargne']['capacite_epargne_estimee'] ?? null);
    }

    public function test_extracts_actifs_immobiliers(): void
    {
        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{"besoins": ["épargne"], "besoins_action": "add", "bae_epargne": {"actifs_immo_total": 300000, "actifs_immo_details": ["résidence principale: 300000"]}}',
                    ],
                ]],
            ], 200),
        ]);

        $data = $this->extractor->extract("Ma résidence principale vaut 300000€");

        $this->assertEquals(300000, $data['bae_epargne']['actifs_immo_total'] ?? null);
        $this->assertIsArray($data['bae_epargne']['actifs_immo_details'] ?? null);
    }

    public function test_extracts_passifs(): void
    {
        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{"besoins": ["épargne"], "besoins_action": "add", "bae_epargne": {"passifs_total_emprunts": 150000, "passifs_details": ["crédit immobilier: 150000"]}}',
                    ],
                ]],
            ], 200),
        ]);

        $data = $this->extractor->extract("J'ai un crédit de 150000€");

        $this->assertEquals(150000, $data['bae_epargne']['passifs_total_emprunts'] ?? null);
    }

    public function test_extracts_donation(): void
    {
        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{"besoins": ["épargne"], "besoins_action": "add", "bae_epargne": {"donation_realisee": true, "donation_montant": 100000, "donation_beneficiaires": "mes enfants"}}',
                    ],
                ]],
            ], 200),
        ]);

        $data = $this->extractor->extract("J'ai fait une donation de 100000€ à mes enfants");

        $this->assertTrue($data['bae_epargne']['donation_realisee'] ?? false);
        $this->assertEquals(100000, $data['bae_epargne']['donation_montant'] ?? null);
    }

    public function test_extracts_actifs_financiers(): void
    {
        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{"besoins": ["épargne"], "besoins_action": "add", "bae_epargne": {"actifs_financiers_total": 50000, "actifs_financiers_details": ["assurance vie: 30000", "PEA: 20000"]}}',
                    ],
                ]],
            ], 200),
        ]);

        $data = $this->extractor->extract("J'ai une assurance vie de 30000€ et un PEA de 20000€");

        $this->assertEquals(50000, $data['bae_epargne']['actifs_financiers_total'] ?? null);
        $this->assertIsArray($data['bae_epargne']['actifs_financiers_details'] ?? null);
    }

    public function test_action_remove_when_negation(): void
    {
        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{"besoins": ["épargne"], "besoins_action": "remove"}',
                    ],
                ]],
            ], 200),
        ]);

        $data = $this->extractor->extract("Je n'ai plus besoin d'épargne");

        $this->assertEquals('remove', $data['besoins_action'] ?? null);
    }

    public function test_returns_empty_when_no_epargne(): void
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

        $data = $this->extractor->extract("Je veux partir à la retraite à 62 ans");

        $this->assertEquals([], $data);
    }

    public function test_returns_empty_on_api_error(): void
    {
        Http::fake([
            'api.mistral.ai/*' => Http::response(['error' => 'Server error'], 500),
        ]);

        $data = $this->extractor->extract("Je veux optimiser mon patrimoine");

        $this->assertEquals([], $data);
    }
}
