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
        if (!Schema::hasColumn('clients', 'km_parcourus_annuels')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->integer('km_parcourus_annuels')->nullable()->after('niveau_activites_sportives');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('clients', 'km_parcourus_annuels')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->dropColumn('km_parcourus_annuels');
            });
        }
    }
};
