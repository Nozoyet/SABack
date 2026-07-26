<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notificacion', function (Blueprint $table) {
            if (!Schema::hasColumn('notificacion', 'tipo')) {
                $table->enum('tipo', [
                    'curso_asignado',
                    'inscripcion_exitosa',
                    'calificacion_asignada',
                ])->after('titulo')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('notificacion', function (Blueprint $table) {
            if (Schema::hasColumn('notificacion', 'tipo')) {
                $table->dropColumn('tipo');
            }
        });
    }
};
