<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        $driver = Schema::getConnection()->getDriverName();

        // SQLite n'a pas de type ENUM, il utilise TEXT/VARCHAR qui accepte toutes les valeurs
        // Cette migration ne sert qu'à MySQL/MariaDB pour étendre l'ENUM
        if ($driver !== 'sqlite') {
            DB::statement("ALTER TABLE audio_records MODIFY COLUMN status ENUM('pending', 'processing', 'done', 'failed', 'pending_review') DEFAULT 'pending'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver !== 'sqlite') {
            DB::statement("ALTER TABLE audio_records MODIFY COLUMN status ENUM('pending', 'processing', 'done', 'failed') DEFAULT 'pending'");
        }
    }
};
