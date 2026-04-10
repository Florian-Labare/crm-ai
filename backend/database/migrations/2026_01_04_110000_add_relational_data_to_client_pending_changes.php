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
        Schema::table('client_pending_changes', function (Blueprint $table) {
            $table->json('relational_data')->nullable()->after('extracted_data')
                ->comment('Données relationnelles: passifs, actifs, BAE, conjoint, enfants');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_pending_changes', function (Blueprint $table) {
            $table->dropColumn('relational_data');
        });
    }
};
