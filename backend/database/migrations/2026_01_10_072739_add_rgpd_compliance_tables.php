<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * RGPD Compliance: Audit trail, source tracking, and data retention
     */
    public function up(): void
    {
        // 1. Audit logs table - Traçabilité complète des opérations
        Schema::create('import_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('import_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('database_connection_id')->nullable()->constrained()->nullOnDelete();

            // Action details
            $table->string('action');                    // upload, connect, import, export, delete, etc.
            $table->string('resource_type');             // session, connection, client, etc.
            $table->unsignedBigInteger('resource_id')->nullable();

            // RGPD specific
            $table->string('legal_basis')->nullable();   // consent, contract, legitimate_interest
            $table->text('legal_basis_details')->nullable(); // Description de la base légale
            $table->boolean('consent_confirmed')->default(false);
            $table->timestamp('consent_timestamp')->nullable();

            // Context
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->json('metadata')->nullable();        // Additional context data

            // Result
            $table->boolean('success')->default(true);
            $table->text('error_message')->nullable();
            $table->integer('records_affected')->default(0);

            $table->timestamps();

            $table->index(['team_id', 'action', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['import_session_id']);
        });

        // 2. Add import source tracking to clients
        Schema::table('clients', function (Blueprint $table) {
            $table->string('import_source')->nullable()->after('user_id');  // manual, file_import, database_import
            $table->foreignId('import_session_id')->nullable()->after('import_source')->constrained()->nullOnDelete();
            $table->timestamp('imported_at')->nullable()->after('import_session_id');
        });

        // 3. Add ephemeral mode and RGPD fields to database_connections
        Schema::table('database_connections', function (Blueprint $table) {
            $table->boolean('is_ephemeral')->default(false)->after('is_active');  // Don't store credentials
            $table->text('purpose')->nullable()->after('is_ephemeral');           // Why this connection exists
            $table->string('data_category')->nullable()->after('purpose');        // personal, sensitive, anonymous
        });

        // 4. Add RGPD consent fields to import_sessions
        Schema::table('import_sessions', function (Blueprint $table) {
            $table->boolean('rgpd_consent_given')->default(false)->after('status');
            $table->string('legal_basis')->nullable()->after('rgpd_consent_given');
            $table->text('legal_basis_details')->nullable()->after('legal_basis');
            $table->timestamp('consent_timestamp')->nullable()->after('legal_basis_details');
            $table->timestamp('retention_until')->nullable()->after('completed_at');  // Auto-delete date
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('import_sessions', function (Blueprint $table) {
            $table->dropColumn(['rgpd_consent_given', 'legal_basis', 'legal_basis_details', 'consent_timestamp', 'retention_until']);
        });

        Schema::table('database_connections', function (Blueprint $table) {
            $table->dropColumn(['is_ephemeral', 'purpose', 'data_category']);
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropForeign(['import_session_id']);
            $table->dropColumn(['import_source', 'import_session_id', 'imported_at']);
        });

        Schema::dropIfExists('import_audit_logs');
    }
};
