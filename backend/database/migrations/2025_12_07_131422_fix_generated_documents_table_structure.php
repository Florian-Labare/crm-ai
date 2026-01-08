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
        // Ajouter les colonnes manquantes
        if (!Schema::hasColumn('generated_documents', 'user_id')) {
            Schema::table('generated_documents', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->after('client_id');
            });
        }
        if (!Schema::hasColumn('generated_documents', 'format')) {
            Schema::table('generated_documents', function (Blueprint $table) {
                $table->string('format')->default('pdf')->after('file_path');
            });
        }
        if (!Schema::hasColumn('generated_documents', 'sent_by_email')) {
            Schema::table('generated_documents', function (Blueprint $table) {
                $table->boolean('sent_by_email')->default(false);
            });
        }
        if (!Schema::hasColumn('generated_documents', 'sent_at')) {
            Schema::table('generated_documents', function (Blueprint $table) {
                $table->timestamp('sent_at')->nullable();
            });
        }

        // Supprimer les colonnes obsolètes
        if (Schema::hasColumn('generated_documents', 'status')) {
            Schema::table('generated_documents', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }
        if (Schema::hasColumn('generated_documents', 'generated_at')) {
            Schema::table('generated_documents', function (Blueprint $table) {
                $table->dropColumn('generated_at');
            });
        }
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
