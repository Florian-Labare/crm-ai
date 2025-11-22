<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tables = [
            'questionnaire_risques',
            'questionnaire_risque_financiers',
            'questionnaire_risque_connaissances',
            'questionnaire_risque_quizzes',
        ];

        if (DB::getDriverName() === 'mysql') {
            foreach ($tables as $table) {
                if (Schema::hasTable($table)) {
                    DB::statement("ALTER TABLE `{$table}` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
                }
            }

            if (Schema::hasTable('questionnaire_risques')) {
                Schema::table('questionnaire_risques', function (Blueprint $table) {
                    $table->text('recommandation')
                        ->charset('utf8mb4')
                        ->collation('utf8mb4_unicode_ci')
                        ->nullable()
                        ->change();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Pas de rollback : conserver l'UTF-8
    }
};
