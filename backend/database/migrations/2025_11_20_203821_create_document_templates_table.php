<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Nom du template (ex: "Recueil Global PP")
            $table->string('description')->nullable();
            $table->string('file_path'); // Chemin vers le template DOCX dans storage
            $table->string('category')->default('reglementaire'); // Type de document
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_templates');
    }
};
