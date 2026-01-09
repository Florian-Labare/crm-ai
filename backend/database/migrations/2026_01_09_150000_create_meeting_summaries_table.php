<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('audio_record_id')->nullable()->constrained('audio_records')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->longText('summary_text')->nullable();
            $table->json('summary_json')->nullable();
            $table->timestamps();

            $table->unique('audio_record_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_summaries');
    }
};
