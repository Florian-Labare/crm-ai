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
        Schema::table('audio_records', function (Blueprint $table) {
            // Données de diarisation
            $table->json('diarization_data')->nullable()->after('transcription')
                ->comment('Données complètes de la diarisation (speakers, segments, stats)');

            // Correction manuelle des speakers
            $table->json('speaker_corrections')->nullable()->after('diarization_data')
                ->comment('Corrections manuelles apportées par l\'utilisateur');

            // Indicateurs de statut diarisation
            $table->boolean('diarization_success')->nullable()->after('speaker_corrections');
            $table->boolean('speakers_corrected')->default(false)->after('diarization_success');
            $table->timestamp('corrected_at')->nullable()->after('speakers_corrected');
            $table->foreignId('corrected_by')->nullable()->after('corrected_at')
                ->constrained('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audio_records', function (Blueprint $table) {
            $table->dropForeign(['corrected_by']);
            $table->dropColumn([
                'diarization_data',
                'speaker_corrections',
                'diarization_success',
                'speakers_corrected',
                'corrected_at',
                'corrected_by'
            ]);
        });
    }
};
