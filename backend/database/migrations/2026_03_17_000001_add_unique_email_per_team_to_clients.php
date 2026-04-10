<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void {
        // 1. Normalisation des emails existants
        DB::statement("UPDATE clients SET email = LOWER(TRIM(email)) WHERE email IS NOT NULL AND email != ''");

        // 2. Détection des doublons (team_id, email)
        $duplicates = DB::select("
            SELECT team_id, email, COUNT(*) as cnt, GROUP_CONCAT(id) as ids
            FROM clients
            WHERE email IS NOT NULL AND email != ''
            GROUP BY team_id, email
            HAVING cnt > 1
        ");

        if (! empty($duplicates)) {
            $log = '=== Doublons clients détectés le '.now()." ===\n";
            foreach ($duplicates as $d) {
                $log .= "team_id={$d->team_id} email={$d->email} → ids: {$d->ids}\n";
            }
            file_put_contents(storage_path('logs/duplicate_clients.log'), $log, FILE_APPEND);

            // Contrainte NON ajoutée — résoudre manuellement puis relancer
            return;
        }

        // 3. Ajout de la contrainte unique (team_id, email)
        // MySQL : les valeurs NULL ne déclenchent pas la contrainte unique
        Schema::table('clients', function (Blueprint $table) {
            $table->unique(['team_id', 'email'], 'clients_team_email_unique');
        });
    }

    public function down(): void {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropUnique('clients_team_email_unique');
        });
    }
};
