<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    /**
     * Resize token/refresh_token to TEXT to accommodate encrypted values.
     * Existing plaintext tokens are nullified so they are re-created on next OAuth login.
     */
    public function up(): void {
        // Nullify existing plaintext tokens before encrypting
        DB::table('social_accounts')->update([
            'token' => null,
            'refresh_token' => null,
        ]);

        Schema::table('social_accounts', function (Blueprint $table) {
            $table->text('token')->nullable()->change();
            $table->text('refresh_token')->nullable()->change();
        });
    }

    public function down(): void {
        Schema::table('social_accounts', function (Blueprint $table) {
            $table->string('token', 1000)->nullable()->change();
            $table->string('refresh_token', 1000)->nullable()->change();
        });
    }
};
