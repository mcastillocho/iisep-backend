<?php

declare(strict_types=1);

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
        Schema::create('asistencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curso_programado_id')->constrained('cursos_programados');
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->date('fecha');
            $table->boolean('asistio');
            $table->text('anotaciones')->nullable();
            $table->foreignId('curso_programado_origen_id')->nullable()->constrained('cursos_programados');

            $table->unique(['curso_programado_id', 'cliente_id', 'fecha'], 'uq_asistencia_diaria');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asistencias');
    }
};
