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
        Schema::create('import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_session_id')->constrained()->onDelete('cascade');
            $table->integer('row_number');
            $table->json('raw_data');
            $table->json('normalized_data')->nullable();
            $table->string('status')->default('pending');
            $table->foreignId('matched_client_id')->nullable()->constrained('clients')->onDelete('set null');
            $table->json('validation_errors')->nullable();
            $table->json('duplicate_matches')->nullable();
            $table->float('duplicate_confidence')->nullable();
            $table->timestamps();

            $table->index(['import_session_id', 'status']);
            $table->index('row_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_rows');
    }
};
