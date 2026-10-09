<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DiaSemana;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $curso_programado_id
 * @property int|null $aula_id
 * @property DiaSemana $dia_semana
 * @property string $hora_inicio
 * @property string $hora_fin
 */
class HorarioCurso extends Model
{
    /**
     * @var string
     */
    protected $table = 'horarios_cursos';

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'curso_programado_id',
        'aula_id',
        'dia_semana',
        'hora_inicio',
        'hora_fin',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'curso_programado_id' => 'integer',
            'aula_id' => 'integer',
            'dia_semana' => DiaSemana::class,
        ];
    }

    /**
     * @return BelongsTo<CursoProgramado, $this>
     */
    public function cursoProgramado(): BelongsTo
    {
        return $this->belongsTo(CursoProgramado::class);
    }

    /**
     * @return BelongsTo<Aula, $this>
     */
    public function aula(): BelongsTo
    {
        return $this->belongsTo(Aula::class);
    }
}
