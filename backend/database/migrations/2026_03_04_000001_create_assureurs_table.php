<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void {
        Schema::create('assureurs', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('lien_espace_client')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('assureurs');
    }
};
