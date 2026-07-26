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
        Schema::table('pensum', function (Blueprint $table) {
            $table->integer('duracion')->nullable()->after('anioCreacion');
            $table->text('descripcion')->nullable()->after('duracion');
            $table->integer('creditos_totales')->nullable()->after('descripcion');
        });
    }

    public function down(): void
    {
        Schema::table('pensum', function (Blueprint $table) {
            $table->dropColumn(['duracion', 'descripcion', 'creditos_totales']);
        });
    }
};
