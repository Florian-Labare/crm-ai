<?php

namespace Tests\Unit;

use App\Http\Resources\ClientRevenuResource;
use App\Models\Client;
use App\Models\ClientRevenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientRevenuResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_resource_returns_correct_structure(): void
    {
        $client = Client::create([
            'nom' => 'Test',
            'prenom' => 'Client',
            'user_id' => null,
        ]);

        $revenu = ClientRevenu::create([
            'client_id' => $client->id,
            'nature' => 'Salaire',
            'periodicite' => 'Mensuel',
            'montant' => 3500.00,
        ]);

        $resource = new ClientRevenuResource($revenu);
        $array = $resource->toArray(request());

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('client_id', $array);
        $this->assertArrayHasKey('nature', $array);
        $this->assertArrayHasKey('periodicite', $array);
        $this->assertArrayHasKey('montant', $array);
        $this->assertArrayHasKey('created_at', $array);
        $this->assertArrayHasKey('updated_at', $array);

        $this->assertEquals($revenu->id, $array['id']);
        $this->assertEquals($client->id, $array['client_id']);
        $this->assertEquals('Salaire', $array['nature']);
        $this->assertEquals('Mensuel', $array['periodicite']);
        $this->assertEquals(3500.00, $array['montant']);
    }

    public function test_resource_handles_null_values(): void
    {
        $client = Client::create([
            'nom' => 'Test',
            'prenom' => 'Client',
            'user_id' => null,
        ]);

        $revenu = ClientRevenu::create([
            'client_id' => $client->id,
            'nature' => null,
            'periodicite' => null,
            'montant' => null,
        ]);

        $resource = new ClientRevenuResource($revenu);
        $array = $resource->toArray(request());

        $this->assertNull($array['nature']);
        $this->assertNull($array['periodicite']);
        $this->assertNull($array['montant']);
    }

    public function test_created_at_is_formatted_as_iso(): void
    {
        $client = Client::create([
            'nom' => 'Test',
            'prenom' => 'Client',
            'user_id' => null,
        ]);

        $revenu = ClientRevenu::create([
            'client_id' => $client->id,
            'nature' => 'Pension',
            'periodicite' => 'Mensuel',
            'montant' => 1200.00,
        ]);

        $resource = new ClientRevenuResource($revenu);
        $array = $resource->toArray(request());

        $this->assertIsString($array['created_at']);
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $array['created_at']);
    }
}
