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
        Schema::create('diarization_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audio_record_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('recording_session_id')->nullable();
            $table->foreignId('team_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');

            // Statut et résultat
            $table->enum('status', ['success', 'failed', 'fallback', 'timeout', 'skipped'])->default('success');
            $table->string('error_message')->nullable();
            $table->string('error_code')->nullable();

            // Métriques de performance
            $table->integer('duration_ms')->nullable()->comment('Durée du traitement en ms');
            $table->integer('audio_duration_seconds')->nullable()->comment('Durée de l\'audio traité');
            $table->integer('file_size_bytes')->nullable();

            // Résultats de la diarisation
            $table->integer('speakers_detected')->nullable();
            $table->string('broker_speaker_id')->nullable();
            $table->json('client_speakers')->nullable();
            $table->float('broker_duration_seconds')->nullable();
            $table->float('client_duration_seconds')->nullable();
            $table->integer('broker_segments_count')->nullable();
            $table->integer('client_segments_count')->nullable();
            $table->boolean('single_speaker_mode')->default(false);

            // Métadonnées
            $table->string('model_version')->nullable()->comment('Version du modèle pyannote utilisé');
            $table->boolean('used_gpu')->default(false);
            $table->json('raw_output')->nullable()->comment('Sortie brute pour debug');

            $table->timestamps();

            // Index pour les requêtes de monitoring
            $table->index(['status', 'created_at']);
            $table->index(['team_id', 'created_at']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('diarization_logs');
    }
};
