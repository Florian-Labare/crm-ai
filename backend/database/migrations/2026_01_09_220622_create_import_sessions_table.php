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
        Schema::create('import_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('import_mapping_id')->nullable()->constrained('import_mappings')->onDelete('set null');
            $table->string('original_filename');
            $table->string('file_path');
            $table->string('status')->default('pending');
            $table->integer('total_rows')->default(0);
            $table->integer('processed_rows')->default(0);
            $table->integer('success_count')->default(0);
            $table->integer('error_count')->default(0);
            $table->integer('duplicate_count')->default(0);
            $table->json('detected_columns')->nullable();
            $table->json('ai_suggested_mappings')->nullable();
            $table->json('errors_summary')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'status']);
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_sessions');
    }
};
