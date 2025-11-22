<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\QuestionnaireRisque;
use App\Services\ScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScoringServiceTest extends TestCase
{
    use RefreshDatabase;

    private ScoringService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(ScoringService::class);
    }

    public function test_score_is_based_on_comportement_only(): void
    {
        $client = Client::create([
            'nom' => 'Test',
            'prenom' => 'Unit',
            'user_id' => null,
        ]);
        $questionnaire = QuestionnaireRisque::create([
            'client_id' => $client->id,
            'score_global' => 0,
            'profil_calcule' => 'Prudent',
            'recommandation' => '',
        ]);

        $dataset = [
            'financier' => [
                'temps_attente_recuperation_valeur' => 'plus_5_ans',
                'niveau_perte_inquietude' => 'pas_inquietude',
                'reaction_baisse_25' => 'acheter_plus',
                'attitude_placements' => 'dynamique',
                'allocation_epargne' => 'allocation_dynamique',
                'objectif_placement' => 'risque_important',
                'horizon_investissement' => 'long_terme',
                'tolerance_risque' => 'elevee',
                'niveau_connaissance_globale' => 'experimente',
                'pourcentage_perte_max' => 'plus_20',
            ],
            'connaissances' => [
                'connaissance_obligations' => true,
                'connaissance_actions' => true,
                'connaissance_girardin' => false,
            ],
        ];

        $questionnaire = $this->service->scorerEtSauvegarder($questionnaire, $dataset);
        $scoreAvecConnaissances = $questionnaire->score_global;

        $questionnaire = $this->service->scorerEtSauvegarder($questionnaire, [
            'financier' => $dataset['financier'],
            'connaissances' => [],
        ]);
        $scoreSansConnaissances = $questionnaire->score_global;

        $this->assertSame(
            $scoreAvecConnaissances,
            $scoreSansConnaissances,
            'Les réponses Connaissances ne doivent pas modifier le score global'
        );
        $this->assertDatabaseHas('questionnaire_risque_connaissances', [
            'questionnaire_risque_id' => $questionnaire->id,
            'connaissance_obligations' => true,
            'connaissance_actions' => true,
        ]);
    }
}
