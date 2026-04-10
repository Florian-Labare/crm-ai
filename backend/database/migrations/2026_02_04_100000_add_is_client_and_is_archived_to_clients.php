<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::table('clients', function (Blueprint $table) {
            $table->boolean('is_client')->default(false)->after('team_id');
            $table->boolean('is_archived')->default(false)->after('is_client');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['is_client', 'is_archived']);
        });
    }
};
