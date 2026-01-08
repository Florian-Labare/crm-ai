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
        Schema::create('client_pending_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('audio_record_id')->nullable()->constrained()->onDelete('set null');

            // Données extraites (avant merge)
            $table->json('extracted_data'); // Toutes les données extraites par GPT
            $table->json('changes_diff');   // Comparaison champ par champ avec valeurs actuelles

            // Statut du workflow
            $table->enum('status', [
                'pending',      // En attente de review
                'reviewing',    // En cours de review
                'approved',     // Validé, prêt à appliquer
                'applied',      // Appliqué au client
                'rejected',     // Rejeté par l'utilisateur
                'partial'       // Partiellement appliqué
            ])->default('pending');

            // Décisions de l'utilisateur (champ par champ)
            $table->json('user_decisions')->nullable(); // {"nom": "accept", "telephone": "reject", ...}

            // Métadonnées
            $table->string('source')->default('audio'); // audio, transcription, import, manual
            $table->text('notes')->nullable(); // Notes de l'utilisateur

            // Timestamps de workflow
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');

            $table->timestamps();

            // Index
            $table->index(['client_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_pending_changes');
    }
};
