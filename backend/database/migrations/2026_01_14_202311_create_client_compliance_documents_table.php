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
        Schema::create('client_compliance_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->onDelete('set null');

            // Type de document réglementaire
            $table->string('document_type'); // cni, avis_imposition, lettre_mission_prevoyance, der_prevoyance, etc.
            $table->string('category'); // identity, fiscal, regulatory

            // Fichier
            $table->string('file_path');
            $table->string('file_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();

            // Statut de validation
            $table->enum('status', ['pending', 'validated', 'rejected', 'expired'])->default('pending');
            $table->timestamp('validated_at')->nullable();
            $table->foreignId('validated_by')->nullable()->constrained('users')->onDelete('set null');

            // Pour les documents avec expiration (CNI, etc.)
            $table->date('expires_at')->nullable();
            $table->date('document_date')->nullable(); // Date du document (ex: date avis d'imposition)

            // Métadonnées
            $table->text('notes')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->timestamps();

            // Index pour les recherches
            $table->index(['client_id', 'document_type']);
            $table->index(['client_id', 'status']);
        });

        // Table pour définir les documents requis par besoin
        Schema::create('compliance_requirements', function (Blueprint $table) {
            $table->id();
            $table->string('besoin'); // prevoyance, retraite, epargne, sante, immobilier, fiscalite
            $table->string('document_type'); // Type de document requis
            $table->string('document_label'); // Label affiché à l'utilisateur
            $table->string('category'); // identity, fiscal, regulatory
            $table->boolean('is_mandatory')->default(true);
            $table->integer('priority')->default(0); // Ordre d'affichage
            $table->timestamps();

            $table->unique(['besoin', 'document_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compliance_requirements');
        Schema::dropIfExists('client_compliance_documents');
    }
};
