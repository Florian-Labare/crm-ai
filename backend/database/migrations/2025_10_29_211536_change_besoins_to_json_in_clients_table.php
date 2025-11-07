<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Convertir les besoins existants de text vers JSON
        $clients = \DB::table('clients')->whereNotNull('besoins')->get();
        foreach ($clients as $client) {
            if ($client->besoins) {
                // Convertir le texte en tableau avec un seul élément
                $besoinsArray = [$client->besoins];
                \DB::table('clients')
                    ->where('id', $client->id)
                    ->update(['besoins' => json_encode($besoinsArray)]);
            }
        }

        // 2. Changer le type de colonne de text vers json
        Schema::table('clients', function (Blueprint $table) {
            $table->json('besoins')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reconvertir JSON vers text
        $clients = \DB::table('clients')->whereNotNull('besoins')->get();
        foreach ($clients as $client) {
            if ($client->besoins) {
                $besoinsArray = json_decode($client->besoins, true);
                $besoinsText = is_array($besoinsArray) ? implode(', ', $besoinsArray) : '';
                \DB::table('clients')
                    ->where('id', $client->id)
                    ->update(['besoins' => $besoinsText]);
            }
        }

        Schema::table('clients', function (Blueprint $table) {
            $table->text('besoins')->nullable()->change();
        });
    }
};
