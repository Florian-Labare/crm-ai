<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class() extends Migration {
    public function up(): void {
        // 1. Rename in team_user pivot (simple string column)
        DB::table('team_user')->where('role', 'member')->update(['role' => 'mia']);
        DB::table('team_user')->where('role', 'viewer')->update(['role' => 'secretaire']);

        // 2. Rename enum values in team_invitations
        //    Step A: expand enum to include both old and new values (so existing data stays valid)
        DB::statement("ALTER TABLE team_invitations MODIFY COLUMN role ENUM('admin','member','viewer','mia','secretaire') NOT NULL DEFAULT 'mia'");
        //    Step B: migrate existing data to new values
        DB::table('team_invitations')->where('role', 'member')->update(['role' => 'mia']);
        DB::table('team_invitations')->where('role', 'viewer')->update(['role' => 'secretaire']);
        //    Step C: shrink enum to only new values
        DB::statement("ALTER TABLE team_invitations MODIFY COLUMN role ENUM('admin','mia','secretaire') NOT NULL DEFAULT 'mia'");
    }

    public function down(): void {
        DB::table('team_user')->where('role', 'mia')->update(['role' => 'member']);
        DB::table('team_user')->where('role', 'secretaire')->update(['role' => 'viewer']);

        // Step A: expand to include both old and new values
        DB::statement("ALTER TABLE team_invitations MODIFY COLUMN role ENUM('admin','member','viewer','mia','secretaire') NOT NULL DEFAULT 'member'");
        // Step B: migrate back
        DB::table('team_invitations')->where('role', 'mia')->update(['role' => 'member']);
        DB::table('team_invitations')->where('role', 'secretaire')->update(['role' => 'viewer']);
        // Step C: shrink to original values
        DB::statement("ALTER TABLE team_invitations MODIFY COLUMN role ENUM('admin','member','viewer') NOT NULL DEFAULT 'member'");
    }
};
