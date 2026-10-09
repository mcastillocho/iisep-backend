<?php

declare(strict_types=1);

use App\Enums\EstadoMatricula;
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
        Schema::create('matriculas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curso_programado_id')->constrained('cursos_programados');
            $table->foreignId('estudiante_id')->constrained('clientes');
            $table->foreignId('estudiante_carrera_id')->nullable()->constrained('estudiantes_carreras');
            $table->foreignId('apoderado_id')->nullable()->constrained('clientes');
            $table->date('fecha_matricula')->useCurrent();
            $table->pgEnum('estado', 'enum_estado_matricula', array_column(EstadoMatricula::cases(), 'value'))->default('preinscrito');

            $table->unique(['curso_programado_id', 'estudiante_id'], 'uq_matricula_estudiante_curso');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('matriculas');
    }
};
