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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');

            // Action et ressource
            $table->string('action', 50); // create, update, delete, access, download, export
            $table->string('resource_type', 100); // AudioRecord, Client, etc.
            $table->unsignedBigInteger('resource_id')->nullable();

            // Description lisible
            $table->string('description');

            // Données avant/après pour les modifications
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();

            // Métadonnées de la requête
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('request_id', 36)->nullable(); // UUID pour tracer les requêtes

            // Catégorie pour filtrage
            $table->string('category', 50)->default('general');
            // audio, client, auth, admin, rgpd

            // Niveau de criticité
            $table->enum('level', ['info', 'warning', 'critical'])->default('info');

            $table->timestamps();

            // Index pour les requêtes fréquentes
            $table->index(['team_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['resource_type', 'resource_id']);
            $table->index(['action', 'created_at']);
            $table->index(['category', 'created_at']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
