<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Éviter les doublons si rejoué
        $exists = DB::table('compliance_requirements')
            ->where('document_type', 'der_signe')
            ->where('besoin', 'any_besoin')
            ->exists();

        if (!$exists) {
            DB::table('compliance_requirements')->insert([
                'besoin'         => 'any_besoin',
                'document_type'  => 'der_signe',
                'document_label' => 'DER signé',
                'category'       => 'regulatory',
                'is_mandatory'   => true,
                'priority'       => 3,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('compliance_requirements')
            ->where('document_type', 'der_signe')
            ->where('besoin', 'any_besoin')
            ->delete();
    }
};
