<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // État Civil - Nouveaux champs
            if (!Schema::hasColumn('clients', 'nom_jeune_fille')) {
                $table->string('nom_jeune_fille')->nullable()->after('nom');
            }
            if (!Schema::hasColumn('clients', 'nationalite')) {
                $table->string('nationalite')->nullable()->after('lieu_naissance');
            }
            if (!Schema::hasColumn('clients', 'date_situation_matrimoniale')) {
                $table->date('date_situation_matrimoniale')->nullable()->after('situation_matrimoniale');
            }
            if (!Schema::hasColumn('clients', 'situation_actuelle')) {
                $table->string('situation_actuelle')->nullable()->after('date_situation_matrimoniale'); // Actif, Retraité, Chômage
            }
            if (!Schema::hasColumn('clients', 'date_evenement_professionnel')) {
                $table->date('date_evenement_professionnel')->nullable()->after('profession'); // Si retraité ou chômage
            }
            if (!Schema::hasColumn('clients', 'risques_professionnels')) {
                $table->boolean('risques_professionnels')->default(false)->after('date_evenement_professionnel');
            }
            if (!Schema::hasColumn('clients', 'details_risques_professionnels')) {
                $table->text('details_risques_professionnels')->nullable()->after('risques_professionnels');
            }

            // Coordonnées
            if (!Schema::hasColumn('clients', 'adresse')) {
                $table->string('adresse')->nullable()->after('details_risques_professionnels');
            }
            if (!Schema::hasColumn('clients', 'code_postal')) {
                $table->string('code_postal')->nullable()->after('adresse');
            }
            if (!Schema::hasColumn('clients', 'ville')) {
                $table->string('ville')->nullable()->after('code_postal');
            }
            if (!Schema::hasColumn('clients', 'residence_fiscale')) {
                $table->string('residence_fiscale')->nullable()->after('ville');
            }
            if (!Schema::hasColumn('clients', 'telephone')) {
                $table->string('telephone')->nullable()->after('residence_fiscale');
            }
            if (!Schema::hasColumn('clients', 'email')) {
                $table->string('email')->nullable()->after('telephone');
            }

            // Mode de vie
            if (!Schema::hasColumn('clients', 'fumeur')) {
                $table->boolean('fumeur')->default(false)->after('email');
            }
            if (!Schema::hasColumn('clients', 'activites_sportives')) {
                $table->boolean('activites_sportives')->default(false)->after('fumeur');
            }
            if (!Schema::hasColumn('clients', 'details_activites_sportives')) {
                $table->text('details_activites_sportives')->nullable()->after('activites_sportives');
            }
            if (!Schema::hasColumn('clients', 'niveau_activites_sportives')) {
                $table->string('niveau_activites_sportives')->nullable()->after('details_activites_sportives');
            }

            // Chargé de clientèle
            if (!Schema::hasColumn('clients', 'charge_clientele')) {
                $table->string('charge_clientele')->nullable()->after('niveau_activites_sportives');
            }
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
