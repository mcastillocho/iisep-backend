<?php

declare(strict_types=1);

use App\Enums\DiaSemana;
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
        Schema::create('horarios_cursos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curso_programado_id')->constrained('cursos_programados')->cascadeOnDelete();
            $table->foreignId('aula_id')->nullable()->constrained('aulas');
            $table->pgEnum('dia_semana', 'enum_dia_semana', array_column(DiaSemana::cases(), 'value'));
            $table->time('hora_inicio');
            $table->time('hora_fin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('horarios_cursos');
    }
};
