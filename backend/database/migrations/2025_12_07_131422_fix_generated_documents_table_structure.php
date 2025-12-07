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
        Schema::table('generated_documents', function (Blueprint $table) {
            // Ajouter les colonnes manquantes
            $table->foreignId('user_id')->after('client_id')->constrained()->onDelete('cascade');
            $table->string('format')->default('pdf')->after('file_path');
            $table->boolean('sent_by_email')->default(false)->after('format');
            $table->timestamp('sent_at')->nullable()->after('sent_by_email');

            // Supprimer les colonnes obsolètes
            $table->dropColumn(['status', 'generated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('generated_documents', function (Blueprint $table) {
            // Remettre les anciennes colonnes
            $table->enum('status', ['pending', 'generated', 'failed'])->default('pending');
            $table->timestamp('generated_at')->nullable();

            // Supprimer les nouvelles colonnes
            $table->dropForeign(['user_id']);
            $table->dropColumn(['user_id', 'format', 'sent_by_email', 'sent_at']);
        });
    }
};
