<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE client_pending_changes MODIFY COLUMN status ENUM(
                'pending',
                'reviewing',
                'approved',
                'applied',
                'rejected',
                'partial',
                'partially_applied'
            ) DEFAULT 'pending'"
        );
    }

    public function down(): void
    {
        DB::statement(
            "ALTER TABLE client_pending_changes MODIFY COLUMN status ENUM(
                'pending',
                'reviewing',
                'approved',
                'applied',
                'rejected',
                'partial'
            ) DEFAULT 'pending'"
        );
    }
};
