<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoMatricula;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $curso_programado_id
 * @property int $estudiante_id
 * @property int|null $estudiante_carrera_id
 * @property int|null $apoderado_id
 * @property Carbon $fecha_matricula
 * @property EstadoMatricula $estado
 */
class Matricula extends Model
{
    /**
     * @var string
     */
    protected $table = 'matriculas';

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'curso_programado_id',
        'estudiante_id',
        'estudiante_carrera_id',
        'apoderado_id',
        'fecha_matricula',
        'estado',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'curso_programado_id' => 'integer',
            'estudiante_id' => 'integer',
            'estudiante_carrera_id' => 'integer',
            'apoderado_id' => 'integer',
            'fecha_matricula' => 'date',
            'estado' => EstadoMatricula::class,
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
     * @return BelongsTo<Cliente, $this>
     */
    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /**
     * @return BelongsTo<EstudianteCarrera, $this>
     */
    public function estudianteCarrera(): BelongsTo
    {
        return $this->belongsTo(EstudianteCarrera::class);
    }

    /**
     * @return BelongsTo<Cliente, $this>
     */
    public function apoderado(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }
}
