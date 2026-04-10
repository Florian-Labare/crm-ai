<?php

namespace Tests\Unit;

use App\Http\Resources\ClientPassifResource;
use App\Models\Client;
use App\Models\ClientPassif;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientPassifResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_resource_returns_correct_structure(): void
    {
        $client = Client::create([
            'nom' => 'Test',
            'prenom' => 'Client',
            'user_id' => null,
        ]);

        $passif = ClientPassif::create([
            'client_id' => $client->id,
            'nature' => 'Crédit immobilier',
            'preteur' => 'Banque XYZ',
            'periodicite' => 'Mensuel',
            'montant_remboursement' => 1200.00,
            'capital_restant_du' => 150000.00,
            'duree_restante' => 180,
        ]);

        $resource = new ClientPassifResource($passif);
        $array = $resource->toArray(request());

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('client_id', $array);
        $this->assertArrayHasKey('nature', $array);
        $this->assertArrayHasKey('preteur', $array);
        $this->assertArrayHasKey('periodicite', $array);
        $this->assertArrayHasKey('montant_remboursement', $array);
        $this->assertArrayHasKey('capital_restant_du', $array);
        $this->assertArrayHasKey('duree_restante', $array);
        $this->assertArrayHasKey('created_at', $array);
        $this->assertArrayHasKey('updated_at', $array);

        $this->assertEquals('Crédit immobilier', $array['nature']);
        $this->assertEquals('Banque XYZ', $array['preteur']);
        $this->assertEquals(1200.00, $array['montant_remboursement']);
        $this->assertEquals(150000.00, $array['capital_restant_du']);
        $this->assertEquals(180, $array['duree_restante']);
    }

    public function test_resource_handles_null_values(): void
    {
        $client = Client::create([
            'nom' => 'Test',
            'prenom' => 'Client',
            'user_id' => null,
        ]);

        $passif = ClientPassif::create([
            'client_id' => $client->id,
        ]);

        $resource = new ClientPassifResource($passif);
        $array = $resource->toArray(request());

        $this->assertNull($array['nature']);
        $this->assertNull($array['preteur']);
        $this->assertNull($array['periodicite']);
        $this->assertNull($array['montant_remboursement']);
    }

    public function test_duree_restante_is_integer(): void
    {
        $client = Client::create([
            'nom' => 'Test',
            'prenom' => 'Client',
            'user_id' => null,
        ]);

        $passif = ClientPassif::create([
            'client_id' => $client->id,
            'duree_restante' => 120,
        ]);

        $resource = new ClientPassifResource($passif);
        $array = $resource->toArray(request());

        $this->assertIsInt($array['duree_restante']);
        $this->assertEquals(120, $array['duree_restante']);
    }
}
