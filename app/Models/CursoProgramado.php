<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $curso_id
 * @property int|null $profesor_id
 * @property Carbon $fecha_inicio
 * @property Carbon $fecha_fin
 */
class CursoProgramado extends Model
{
    /**
     * @var string
     */
    protected $table = 'cursos_programados';

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'curso_id',
        'profesor_id',
        'fecha_inicio',
        'fecha_fin',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'curso_id' => 'integer',
            'profesor_id' => 'integer',
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Curso, $this>
     */
    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    /**
     * @return BelongsTo<Profesor, $this>
     */
    public function profesor(): BelongsTo
    {
        return $this->belongsTo(Profesor::class);
    }

    /**
     * @return HasMany<HorarioCurso, $this>
     */
    public function horarios(): HasMany
    {
        return $this->hasMany(HorarioCurso::class);
    }

    /**
     * @return HasMany<Matricula, $this>
     */
    public function matriculas(): HasMany
    {
        return $this->hasMany(Matricula::class);
    }

    /**
     * @return HasMany<Asistencia, $this>
     */
    public function asistencias(): HasMany
    {
        return $this->hasMany(Asistencia::class);
    }

    /**
     * @return HasMany<Asistencia, $this>
     */
    public function asistenciasOrigen(): HasMany
    {
        return $this->hasMany(Asistencia::class, 'curso_programado_origen_id');
    }
}
