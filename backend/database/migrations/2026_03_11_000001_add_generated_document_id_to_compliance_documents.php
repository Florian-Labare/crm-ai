<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_compliance_documents', function (Blueprint $table) {
            $table->foreignId('generated_document_id')
                ->nullable()
                ->after('client_id')
                ->constrained('generated_documents')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('client_compliance_documents', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\GeneratedDocument::class, 'generated_document_id');
            $table->dropColumn('generated_document_id');
        });
    }
};
