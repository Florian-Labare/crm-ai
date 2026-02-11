<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recording_sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('audio_record_id')->nullable()->after('client_id');
            $table->foreign('audio_record_id')->references('id')->on('audio_records')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('recording_sessions', function (Blueprint $table) {
            $table->dropForeign(['audio_record_id']);
            $table->dropColumn('audio_record_id');
        });
    }
};
