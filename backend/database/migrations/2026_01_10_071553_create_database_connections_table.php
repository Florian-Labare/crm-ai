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
        Schema::create('database_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->string('name');                           // "Base Cabinet Durand"
            $table->string('driver');                         // mysql, pgsql, sqlite, sqlsrv
            $table->string('host')->nullable();               // hostname or IP
            $table->integer('port')->nullable();              // port number
            $table->string('database');                       // database name or file path
            $table->string('username')->nullable();           // username for auth
            $table->text('password_encrypted')->nullable();   // encrypted password
            $table->string('schema')->nullable();             // for PostgreSQL
            $table->json('options')->nullable();              // additional options (charset, etc.)
            $table->timestamp('last_tested_at')->nullable();  // last successful connection test
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['team_id', 'is_active']);
        });

        // Add source_type 'database' to import_sessions
        Schema::table('import_sessions', function (Blueprint $table) {
            $table->foreignId('database_connection_id')
                ->nullable()
                ->after('import_mapping_id')
                ->constrained('database_connections')
                ->nullOnDelete();
            $table->string('source_table')->nullable()->after('database_connection_id');
            $table->text('source_query')->nullable()->after('source_table');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('import_sessions', function (Blueprint $table) {
            $table->dropForeign(['database_connection_id']);
            $table->dropColumn(['database_connection_id', 'source_table', 'source_query']);
        });

        Schema::dropIfExists('database_connections');
    }
};
