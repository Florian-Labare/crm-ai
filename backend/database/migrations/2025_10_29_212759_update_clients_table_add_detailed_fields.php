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
        Schema::table('clients', function (Blueprint $table) {
            // État Civil - Nouveaux champs
            $table->string('nom_jeune_fille')->nullable()->after('nom');
            $table->string('nationalite')->nullable()->after('lieudenaissance');
            $table->date('date_situation_matrimoniale')->nullable()->after('situationmatrimoniale');
            $table->string('situation_actuelle')->nullable()->after('date_situation_matrimoniale'); // Actif, Retraité, Chômage
            $table->date('date_evenement_professionnel')->nullable()->after('profession'); // Si retraité ou chômage
            $table->boolean('risques_professionnels')->default(false)->after('date_evenement_professionnel');
            $table->text('details_risques_professionnels')->nullable()->after('risques_professionnels');

            // Coordonnées
            $table->string('adresse')->nullable()->after('details_risques_professionnels');
            $table->string('code_postal')->nullable()->after('adresse');
            $table->string('ville')->nullable()->after('code_postal');
            $table->string('residence_fiscale')->nullable()->after('ville');
            $table->string('telephone')->nullable()->after('residence_fiscale');
            $table->string('email')->nullable()->after('telephone');

            // Mode de vie
            $table->boolean('fumeur')->default(false)->after('email');
            $table->boolean('activites_sportives')->default(false)->after('fumeur');
            $table->text('details_activites_sportives')->nullable()->after('activites_sportives');
            $table->string('niveau_activites_sportives')->nullable()->after('details_activites_sportives');

            // Chargé de clientèle
            $table->string('charge_clientele')->nullable()->after('niveau_activites_sportives');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn([
                'nom_jeune_fille',
                'nationalite',
                'date_situation_matrimoniale',
                'situation_actuelle',
                'date_evenement_professionnel',
                'risques_professionnels',
                'details_risques_professionnels',
                'adresse',
                'code_postal',
                'ville',
                'residence_fiscale',
                'telephone',
                'email',
                'fumeur',
                'activites_sportives',
                'details_activites_sportives',
                'niveau_activites_sportives',
                'charge_clientele',
            ]);
        });
    }
};
