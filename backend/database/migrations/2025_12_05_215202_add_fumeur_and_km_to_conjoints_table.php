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
        Schema::table('conjoints', function (Blueprint $table) {
            $table->string('fumeur')->nullable()->after('ville');
            $table->integer('km_parcourus_annuels')->nullable()->after('fumeur');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conjoints', function (Blueprint $table) {
            $table->dropColumn(['fumeur', 'km_parcourus_annuels']);
        });
    }
};
