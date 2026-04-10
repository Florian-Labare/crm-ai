<?php

namespace Tests\Unit;

use App\Http\Resources\ClientBienImmobilierResource;
use App\Models\Client;
use App\Models\ClientBienImmobilier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientBienImmobilierResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_resource_returns_correct_structure(): void
    {
        $client = Client::create([
            'nom' => 'Test',
            'prenom' => 'Client',
            'user_id' => null,
        ]);

        $bien = ClientBienImmobilier::create([
            'client_id' => $client->id,
            'designation' => 'Appartement Paris 15e',
            'detenteur' => 'M. et Mme Test',
            'forme_propriete' => 'Indivision',
            'valeur_actuelle_estimee' => 350000.00,
            'annee_acquisition' => 2015,
            'valeur_acquisition' => 280000.00,
        ]);

        $resource = new ClientBienImmobilierResource($bien);
        $array = $resource->toArray(request());

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('client_id', $array);
        $this->assertArrayHasKey('designation', $array);
        $this->assertArrayHasKey('detenteur', $array);
        $this->assertArrayHasKey('forme_propriete', $array);
        $this->assertArrayHasKey('valeur_actuelle_estimee', $array);
        $this->assertArrayHasKey('annee_acquisition', $array);
        $this->assertArrayHasKey('valeur_acquisition', $array);
        $this->assertArrayHasKey('created_at', $array);
        $this->assertArrayHasKey('updated_at', $array);

        $this->assertEquals('Appartement Paris 15e', $array['designation']);
        $this->assertEquals('M. et Mme Test', $array['detenteur']);
        $this->assertEquals('Indivision', $array['forme_propriete']);
        $this->assertEquals(350000.00, $array['valeur_actuelle_estimee']);
        $this->assertEquals(2015, $array['annee_acquisition']);
        $this->assertEquals(280000.00, $array['valeur_acquisition']);
    }

    public function test_resource_handles_null_values(): void
    {
        $client = Client::create([
            'nom' => 'Test',
            'prenom' => 'Client',
            'user_id' => null,
        ]);

        $bien = ClientBienImmobilier::create([
            'client_id' => $client->id,
        ]);

        $resource = new ClientBienImmobilierResource($bien);
        $array = $resource->toArray(request());

        $this->assertNull($array['designation']);
        $this->assertNull($array['detenteur']);
        $this->assertNull($array['forme_propriete']);
        $this->assertNull($array['valeur_actuelle_estimee']);
        $this->assertNull($array['annee_acquisition']);
        $this->assertNull($array['valeur_acquisition']);
    }

    public function test_annee_acquisition_is_integer(): void
    {
        $client = Client::create([
            'nom' => 'Test',
            'prenom' => 'Client',
            'user_id' => null,
        ]);

        $bien = ClientBienImmobilier::create([
            'client_id' => $client->id,
            'annee_acquisition' => 2020,
        ]);

        $resource = new ClientBienImmobilierResource($bien);
        $array = $resource->toArray(request());

        $this->assertIsInt($array['annee_acquisition']);
        $this->assertEquals(2020, $array['annee_acquisition']);
    }
}
