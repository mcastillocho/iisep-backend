<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoCarreraEstudiante;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $estudiante_id
 * @property int $carrera_id
 * @property Carbon $fecha_ingreso
 * @property EstadoCarreraEstudiante $estado
 */
class EstudianteCarrera extends Model
{
    /**
     * @var string
     */
    protected $table = 'estudiantes_carreras';

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'estudiante_id',
        'carrera_id',
        'fecha_ingreso',
        'estado',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estudiante_id' => 'integer',
            'carrera_id' => 'integer',
            'fecha_ingreso' => 'date',
            'estado' => EstadoCarreraEstudiante::class,
        ];
    }

    /**
     * @return BelongsTo<Cliente, $this>
     */
    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /**
     * @return BelongsTo<Carrera, $this>
     */
    public function carrera(): BelongsTo
    {
        return $this->belongsTo(Carrera::class);
    }

    /**
     * @return HasMany<Matricula, $this>
     */
    public function matriculas(): HasMany
    {
        return $this->hasMany(Matricula::class);
    }
}
