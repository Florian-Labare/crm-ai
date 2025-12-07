#!/bin/bash

# Script pour créer toutes les tables manquantes
docker-compose exec backend php artisan tinker <<'EOF'

// 1. Table teams
Schema::create('teams', function ($table) {
    $table->id();
    $table->foreignId('user_id')->index();
    $table->string('name');
    $table->boolean('personal_team');
    $table->timestamps();
});
echo "✓ teams created\n";

// 2. Table recording_sessions
Schema::create('recording_sessions', function ($table) {
    $table->id();
    $table->uuid('session_id')->unique();
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->foreignId('client_id')->nullable()->constrained()->onDelete('cascade');
    $table->foreignId('team_id')->nullable();
    $table->integer('total_chunks')->default(0);
    $table->text('final_transcription')->nullable();
    $table->enum('status', ['recording', 'processing', 'completed', 'failed'])->default('recording');
    $table->timestamp('started_at')->nullable();
    $table->timestamp('finalized_at')->nullable();
    $table->timestamps();
});
echo "✓ recording_sessions created\n";

// 3. Table generated_documents
Schema::create('generated_documents', function ($table) {
    $table->id();
    $table->foreignId('client_id')->constrained()->onDelete('cascade');
    $table->foreignId('document_template_id')->constrained()->onDelete('cascade');
    $table->string('file_path');
    $table->enum('status', ['pending', 'generated', 'failed'])->default('pending');
    $table->timestamp('generated_at')->nullable();
    $table->timestamps();
});
echo "✓ generated_documents created\n";

// 4. Table team_user
Schema::create('team_user', function ($table) {
    $table->id();
    $table->foreignId('team_id');
    $table->foreignId('user_id');
    $table->string('role')->nullable();
    $table->timestamps();
    $table->unique(['team_id', 'user_id']);
});
echo "✓ team_user created\n";

// 5-9. Tables client (nouvelles)
Schema::create('client_revenus', function ($table) {
    $table->id();
    $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
    $table->string('nature')->nullable();
    $table->string('periodicite')->nullable();
    $table->decimal('montant', 12, 2)->nullable();
    $table->timestamps();
});
echo "✓ client_revenus created\n";

Schema::create('client_passifs', function ($table) {
    $table->id();
    $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
    $table->string('nature')->nullable();
    $table->string('preteur')->nullable();
    $table->string('periodicite')->nullable();
    $table->decimal('montant_remboursement', 12, 2)->nullable();
    $table->decimal('capital_restant_du', 12, 2)->nullable();
    $table->integer('duree_restante')->nullable();
    $table->timestamps();
});
echo "✓ client_passifs created\n";

Schema::create('client_actifs_financiers', function ($table) {
    $table->id();
    $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
    $table->string('nature')->nullable();
    $table->string('etablissement')->nullable();
    $table->string('detenteur')->nullable();
    $table->date('date_ouverture_souscription')->nullable();
    $table->decimal('valeur_actuelle', 12, 2)->nullable();
    $table->timestamps();
});
echo "✓ client_actifs_financiers created\n";

Schema::create('client_biens_immobiliers', function ($table) {
    $table->id();
    $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
    $table->string('designation')->nullable();
    $table->string('detenteur')->nullable();
    $table->string('forme_propriete')->nullable();
    $table->decimal('valeur_actuelle_estimee', 12, 2)->nullable();
    $table->integer('annee_acquisition')->nullable();
    $table->decimal('valeur_acquisition', 12, 2)->nullable();
    $table->timestamps();
});
echo "✓ client_biens_immobiliers created\n";

Schema::create('client_autres_epargnes', function ($table) {
    $table->id();
    $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
    $table->string('designation')->nullable();
    $table->string('detenteur')->nullable();
    $table->decimal('valeur', 12, 2)->nullable();
    $table->timestamps();
});
echo "✓ client_autres_epargnes created\n";

echo "\n✅ Toutes les tables créées avec succès!\n";
EOF
