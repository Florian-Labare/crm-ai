<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            foreach (['chef_entreprise' => 'boolean', 'statut' => 'string', 'travailleur_independant' => 'boolean', 'mandataire_social' => 'boolean'] as $column => $type) {
                if (!Schema::hasColumn('clients', $column)) {
                    if ($type === 'boolean') {
                        $table->boolean($column)->default(false)->after('charge_clientele');
                    } else {
                        $table->string($column)->nullable()->after('charge_clientele');
                    }
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            foreach (['chef_entreprise', 'statut', 'travailleur_independant', 'mandataire_social'] as $column) {
                if (Schema::hasColumn('clients', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
