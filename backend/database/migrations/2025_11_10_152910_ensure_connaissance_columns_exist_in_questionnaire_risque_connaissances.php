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
        Schema::table('questionnaire_risque_connaissances', function (Blueprint $table) {
            if (!Schema::hasColumn('questionnaire_risque_connaissances', 'questionnaire_risque_id')) {
                $table->unsignedBigInteger('questionnaire_risque_id')->nullable()->after('id');
                $table->foreign('questionnaire_risque_id', 'qr_connaissance_qr_id_fk_fix')
                    ->references('id')
                    ->on('questionnaire_risques')
                    ->onDelete('cascade');
            }

            $booleanColumns = [
                'connaissance_obligations',
                'connaissance_actions',
                'connaissance_fip_fcpi',
                'connaissance_opci_scpi',
                'connaissance_produits_structures',
                'connaissance_monetaires',
                'connaissance_parts_sociales',
                'connaissance_titres_participatifs',
                'connaissance_fps_slp',
                'connaissance_girardin',
            ];

            foreach ($booleanColumns as $column) {
                if (!Schema::hasColumn('questionnaire_risque_connaissances', $column)) {
                    $table->boolean($column)->default(false)->after('questionnaire_risque_id');
                }

                $montantColumn = str_replace('connaissance_', 'montant_', $column);
                if (!Schema::hasColumn('questionnaire_risque_connaissances', $montantColumn)) {
                    $table->decimal($montantColumn, 15, 2)->nullable()->after($column);
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('questionnaire_risque_connaissances', function (Blueprint $table) {
            //
        });
    }
};
