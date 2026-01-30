<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Ajoute les champs manquants pour supporter l'import complet des données conjoint
     */
    public function up(): void
    {
        $columnsToAdd = [
            'email' => fn(Blueprint $table) => $table->string('email')->nullable(),
            'code_postal' => fn(Blueprint $table) => $table->string('code_postal')->nullable(),
            'ville' => fn(Blueprint $table) => $table->string('ville')->nullable(),
            'situation_professionnelle' => fn(Blueprint $table) => $table->string('situation_professionnelle')->nullable(),
            'situation_chomage' => fn(Blueprint $table) => $table->string('situation_chomage')->nullable(),
            'statut' => fn(Blueprint $table) => $table->string('statut')->nullable(),
            'travailleur_independant' => fn(Blueprint $table) => $table->boolean('travailleur_independant')->default(false),
            'mandataire_social' => fn(Blueprint $table) => $table->boolean('mandataire_social')->default(false),
            'fumeur' => fn(Blueprint $table) => $table->boolean('fumeur')->default(false),
            'activites_sportives' => fn(Blueprint $table) => $table->boolean('activites_sportives')->default(false),
            'niveau_activite_sportive' => fn(Blueprint $table) => $table->string('niveau_activite_sportive')->nullable(),
            'details_activites_sportives' => fn(Blueprint $table) => $table->text('details_activites_sportives')->nullable(),
            'km_parcourus_annuels' => fn(Blueprint $table) => $table->integer('km_parcourus_annuels')->nullable(),
            'revenus_annuels' => fn(Blueprint $table) => $table->decimal('revenus_annuels', 15, 2)->nullable(),
        ];

        foreach ($columnsToAdd as $column => $definition) {
            if (!Schema::hasColumn('conjoints', $column)) {
                Schema::table('conjoints', function (Blueprint $table) use ($definition) {
                    $definition($table);
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $columns = [
            'email',
            'code_postal',
            'ville',
            'situation_professionnelle',
            'situation_chomage',
            'statut',
            'travailleur_independant',
            'mandataire_social',
            'fumeur',
            'activites_sportives',
            'niveau_activite_sportive',
            'details_activites_sportives',
            'km_parcourus_annuels',
            'revenus_annuels',
        ];

        foreach ($columns as $column) {
            if (Schema::hasColumn('conjoints', $column)) {
                Schema::table('conjoints', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
