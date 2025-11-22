<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            foreach ([
                'datedenaissance' => 'date_naissance',
                'lieudenaissance' => 'lieu_naissance',
                'situationmatrimoniale' => 'situation_matrimoniale',
                'revenusannuels' => 'revenus_annuels',
                'nombreenfants' => 'nombre_enfants',
            ] as $old => $new) {
                if (Schema::hasColumn('clients', $old)) {
                    $table->renameColumn($old, $new);
                }
            }
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE clients MODIFY date_naissance VARCHAR(255) NULL');
            DB::statement('ALTER TABLE clients MODIFY lieu_naissance VARCHAR(255) NULL');
            DB::statement('ALTER TABLE clients MODIFY situation_matrimoniale VARCHAR(255) NULL');
            DB::statement('ALTER TABLE clients MODIFY date_situation_matrimoniale VARCHAR(255) NULL');
            DB::statement('ALTER TABLE clients MODIFY date_evenement_professionnel VARCHAR(255) NULL');
            DB::statement('ALTER TABLE clients MODIFY revenus_annuels VARCHAR(255) NULL');
        }

        Schema::table('conjoints', function (Blueprint $table) {
            foreach ([
                'datedenaissance' => 'date_naissance',
                'lieudenaissance' => 'lieu_naissance',
            ] as $old => $new) {
                if (Schema::hasColumn('conjoints', $old)) {
                    $table->renameColumn($old, $new);
                }
            }
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE conjoints MODIFY nom VARCHAR(255) NULL');
            DB::statement('ALTER TABLE conjoints MODIFY prenom VARCHAR(255) NULL');
            DB::statement('ALTER TABLE conjoints MODIFY date_naissance VARCHAR(255) NULL');
            DB::statement('ALTER TABLE conjoints MODIFY lieu_naissance VARCHAR(255) NULL');
            DB::statement('ALTER TABLE conjoints MODIFY date_evenement_professionnel VARCHAR(255) NULL');
        }

        Schema::table('enfants', function (Blueprint $table) {
            if (Schema::hasColumn('enfants', 'datedenaissance')) {
                $table->renameColumn('datedenaissance', 'date_naissance');
            }
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE enfants MODIFY nom VARCHAR(255) NULL');
            DB::statement('ALTER TABLE enfants MODIFY prenom VARCHAR(255) NULL');
            DB::statement('ALTER TABLE enfants MODIFY date_naissance VARCHAR(255) NULL');
        }
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->renameColumn('date_naissance', 'datedenaissance');
            $table->renameColumn('lieu_naissance', 'lieudenaissance');
            $table->renameColumn('situation_matrimoniale', 'situationmatrimoniale');
            $table->renameColumn('revenus_annuels', 'revenusannuels');
            $table->renameColumn('nombre_enfants', 'nombreenfants');
        });

        Schema::table('conjoints', function (Blueprint $table) {
            $table->renameColumn('date_naissance', 'datedenaissance');
            $table->renameColumn('lieu_naissance', 'lieudenaissance');
        });

        Schema::table('enfants', function (Blueprint $table) {
            $table->renameColumn('date_naissance', 'datedenaissance');
        });
    }
};
