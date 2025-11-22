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
        Schema::table('questionnaire_risque_financiers', function (Blueprint $table) {
            if (!Schema::hasColumn('questionnaire_risque_financiers', 'objectifs_rapport')) {
                $table->text('objectifs_rapport')->nullable()->after('objectif_global');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('questionnaire_risque_financiers', function (Blueprint $table) {
            if (Schema::hasColumn('questionnaire_risque_financiers', 'objectifs_rapport')) {
                $table->dropColumn('objectifs_rapport');
            }
        });
    }
};
