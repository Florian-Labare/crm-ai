<?php

namespace Tests\Unit;

use App\Http\Resources\ClientActifFinancierResource;
use App\Models\Client;
use App\Models\ClientActifFinancier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientActifFinancierResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_resource_returns_correct_structure(): void
    {
        $client = Client::create([
            'nom' => 'Test',
            'prenom' => 'Client',
            'user_id' => null,
        ]);

        $actif = ClientActifFinancier::create([
            'client_id' => $client->id,
            'nature' => 'Assurance-vie',
            'etablissement' => 'Assureur ABC',
            'detenteur' => 'Client Test',
            'date_ouverture_souscription' => '2020-01-15',
            'valeur_actuelle' => 50000.00,
        ]);

        $resource = new ClientActifFinancierResource($actif);
        $array = $resource->toArray(request());

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('client_id', $array);
        $this->assertArrayHasKey('nature', $array);
        $this->assertArrayHasKey('etablissement', $array);
        $this->assertArrayHasKey('detenteur', $array);
        $this->assertArrayHasKey('date_ouverture_souscription', $array);
        $this->assertArrayHasKey('valeur_actuelle', $array);
        $this->assertArrayHasKey('created_at', $array);
        $this->assertArrayHasKey('updated_at', $array);

        $this->assertEquals('Assurance-vie', $array['nature']);
        $this->assertEquals('Assureur ABC', $array['etablissement']);
        $this->assertEquals('Client Test', $array['detenteur']);
        $this->assertEquals(50000.00, $array['valeur_actuelle']);
    }

    public function test_resource_handles_null_values(): void
    {
        $client = Client::create([
            'nom' => 'Test',
            'prenom' => 'Client',
            'user_id' => null,
        ]);

        $actif = ClientActifFinancier::create([
            'client_id' => $client->id,
        ]);

        $resource = new ClientActifFinancierResource($actif);
        $array = $resource->toArray(request());

        $this->assertNull($array['nature']);
        $this->assertNull($array['etablissement']);
        $this->assertNull($array['detenteur']);
        $this->assertNull($array['date_ouverture_souscription']);
        $this->assertNull($array['valeur_actuelle']);
    }

    public function test_date_ouverture_is_formatted_correctly(): void
    {
        $client = Client::create([
            'nom' => 'Test',
            'prenom' => 'Client',
            'user_id' => null,
        ]);

        $actif = ClientActifFinancier::create([
            'client_id' => $client->id,
            'date_ouverture_souscription' => '2022-06-15',
        ]);

        $resource = new ClientActifFinancierResource($actif);
        $array = $resource->toArray(request());

        $this->assertEquals('2022-06-15', $array['date_ouverture_souscription']);
    }
}
