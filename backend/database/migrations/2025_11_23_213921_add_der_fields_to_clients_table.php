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
            $table->foreignId('der_charge_clientele_id')
                ->nullable()
                ->after('user_id')
                ->constrained('users')
                ->onDelete('set null');

            $table->string('der_lieu_rdv')->nullable()->after('der_charge_clientele_id');
            $table->date('der_date_rdv')->nullable()->after('der_lieu_rdv');
            $table->time('der_heure_rdv')->nullable()->after('der_date_rdv');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropForeign(['der_charge_clientele_id']);
            $table->dropColumn([
                'der_charge_clientele_id',
                'der_lieu_rdv',
                'der_date_rdv',
                'der_heure_rdv',
            ]);
        });
    }
};
