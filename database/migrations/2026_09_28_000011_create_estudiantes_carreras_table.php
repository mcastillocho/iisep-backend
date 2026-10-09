<?php

declare(strict_types=1);

use App\Enums\EstadoCarreraEstudiante;
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
        Schema::create('estudiantes_carreras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estudiante_id')->constrained('clientes')->cascadeOnDelete();
            $table->foreignId('carrera_id')->constrained('carreras');
            $table->date('fecha_ingreso')->useCurrent();
            $table->pgEnum('estado', 'enum_estado_carrera_estudiante', array_column(EstadoCarreraEstudiante::cases(), 'value'))->default('activo');

            $table->unique(['estudiante_id', 'carrera_id'], 'uq_estudiante_carrera');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('estudiantes_carreras');
    }
};
