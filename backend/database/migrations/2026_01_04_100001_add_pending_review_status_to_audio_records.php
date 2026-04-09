<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Modifier l'ENUM pour ajouter pending_review
        DB::statement("ALTER TABLE audio_records MODIFY COLUMN status ENUM('pending', 'processing', 'done', 'failed', 'pending_review') DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remettre l'ancien ENUM (attention: les enregistrements avec pending_review seront perdus)
        DB::statement("ALTER TABLE audio_records MODIFY COLUMN status ENUM('pending', 'processing', 'done', 'failed') DEFAULT 'pending'");
    }
};
