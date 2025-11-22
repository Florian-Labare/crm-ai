<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('nom')->nullable();
            $table->string('prenom')->nullable();
            $table->string('date_naissance')->nullable();
            $table->string('lieu_naissance')->nullable();
            $table->string('situation_matrimoniale')->nullable();
            $table->string('profession')->nullable();
            $table->decimal('revenus_annuel', 12, 2)->nullable();
            $table->integer('nombre_end')->nullable();
            $table->text('besoins')->nullable();
            $table->string('transcription_path')->nullable();
            $table->boolean('consentement_audio')->default(false);
            $table->timestamps();
        });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
