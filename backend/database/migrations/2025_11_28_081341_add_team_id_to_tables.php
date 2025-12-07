<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (!Schema::hasColumn('clients', 'team_id')) {
                $table->foreignId('team_id')->nullable()->after('id')->index();
            }
        });
        Schema::table('audio_records', function (Blueprint $table) {
            if (!Schema::hasColumn('audio_records', 'team_id')) {
                $table->foreignId('team_id')->nullable()->after('id')->index();
            }
        });
        Schema::table('document_templates', function (Blueprint $table) {
            if (!Schema::hasColumn('document_templates', 'team_id')) {
                $table->foreignId('team_id')->nullable()->after('id')->index();
            }
        });
        Schema::table('questionnaire_risques', function (Blueprint $table) {
            if (!Schema::hasColumn('questionnaire_risques', 'team_id')) {
                $table->foreignId('team_id')->nullable()->after('id')->index();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (Schema::hasColumn('clients', 'team_id')) {
                $table->dropColumn('team_id');
            }
        });
        Schema::table('audio_records', function (Blueprint $table) {
            if (Schema::hasColumn('audio_records', 'team_id')) {
                $table->dropColumn('team_id');
            }
        });
        Schema::table('document_templates', function (Blueprint $table) {
            if (Schema::hasColumn('document_templates', 'team_id')) {
                $table->dropColumn('team_id');
            }
        });
        Schema::table('questionnaire_risques', function (Blueprint $table) {
            if (Schema::hasColumn('questionnaire_risques', 'team_id')) {
                $table->dropColumn('team_id');
            }
        });
    }
};
