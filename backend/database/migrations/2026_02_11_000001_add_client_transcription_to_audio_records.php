<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void {
        Schema::table('audio_records', function (Blueprint $table) {
            $table->longText('client_transcription')->nullable()->after('transcription');
        });
    }

    public function down(): void {
        Schema::table('audio_records', function (Blueprint $table) {
            $table->dropColumn('client_transcription');
        });
    }
};
