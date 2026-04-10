<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        // Ajouter colonnes tags et custom_label sur client_compliance_documents
        if (! Schema::hasColumn('client_compliance_documents', 'tags')) {
            Schema::table('client_compliance_documents', function (Blueprint $table) {
                $table->json('tags')->nullable()->after('category');
                $table->string('custom_label')->nullable()->after('tags');
            });
        }

        // Table de liaison document ↔ requirement (many-to-many avec pivot enrichi)
        if (! Schema::hasTable('compliance_document_requirements')) {
            Schema::create('compliance_document_requirements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('document_id')->constrained('client_compliance_documents')->cascadeOnDelete();
                $table->foreignId('requirement_id')->constrained('compliance_requirements')->cascadeOnDelete();
                $table->enum('status', ['pending', 'validated', 'rejected'])->default('pending');
                $table->timestamp('validated_at')->nullable();
                $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['document_id', 'requirement_id'], 'doc_req_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('compliance_document_requirements');

        Schema::table('client_compliance_documents', function (Blueprint $table) {
            $table->dropColumn(['tags', 'custom_label']);
        });
    }
};
