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
        Schema::table('client_revenus', function (Blueprint $table) {
            $table->string('details')->nullable()->after('nature')->comment('Précision sur la nature du revenu (obligatoire si nature=autre)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_revenus', function (Blueprint $table) {
            $table->dropColumn('details');
        });
    }
};
