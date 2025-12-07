<?php

namespace Tests\Unit;

use App\Http\Resources\ClientAutreEpargneResource;
use App\Models\Client;
use App\Models\ClientAutreEpargne;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientAutreEpargneResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_resource_returns_correct_structure(): void
    {
        $client = Client::create([
            'nom' => 'Test',
            'prenom' => 'Client',
            'user_id' => null,
        ]);

        $epargne = ClientAutreEpargne::create([
            'client_id' => $client->id,
            'designation' => 'Or physique',
            'detenteur' => 'Client Test',
            'valeur' => 15000.00,
        ]);

        $resource = new ClientAutreEpargneResource($epargne);
        $array = $resource->toArray(request());

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('client_id', $array);
        $this->assertArrayHasKey('designation', $array);
        $this->assertArrayHasKey('detenteur', $array);
        $this->assertArrayHasKey('valeur', $array);
        $this->assertArrayHasKey('created_at', $array);
        $this->assertArrayHasKey('updated_at', $array);

        $this->assertEquals('Or physique', $array['designation']);
        $this->assertEquals('Client Test', $array['detenteur']);
        $this->assertEquals(15000.00, $array['valeur']);
    }

    public function test_resource_handles_null_values(): void
    {
        $client = Client::create([
            'nom' => 'Test',
            'prenom' => 'Client',
            'user_id' => null,
        ]);

        $epargne = ClientAutreEpargne::create([
            'client_id' => $client->id,
        ]);

        $resource = new ClientAutreEpargneResource($epargne);
        $array = $resource->toArray(request());

        $this->assertNull($array['designation']);
        $this->assertNull($array['detenteur']);
        $this->assertNull($array['valeur']);
    }

    public function test_valeur_is_decimal(): void
    {
        $client = Client::create([
            'nom' => 'Test',
            'prenom' => 'Client',
            'user_id' => null,
        ]);

        $epargne = ClientAutreEpargne::create([
            'client_id' => $client->id,
            'designation' => 'Cryptomonnaies',
            'valeur' => 8500.50,
        ]);

        $resource = new ClientAutreEpargneResource($epargne);
        $array = $resource->toArray(request());

        $this->assertEquals(8500.50, $array['valeur']);
    }
}
