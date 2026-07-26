<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('impersonation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('idAdmin')->constrained('usuario')->cascadeOnDelete();
            $table->foreignId('idUsuarioObjetivo')->constrained('usuario')->cascadeOnDelete();
            $table->timestamp('fechaInicio')->useCurrent();
            $table->timestamp('fechaFin')->nullable();
            $table->string('ip', 45)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('impersonation_logs');
    }
};