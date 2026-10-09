<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $curso_programado_id
 * @property int $cliente_id
 * @property Carbon $fecha
 * @property bool $asistio
 * @property string|null $anotaciones
 * @property int|null $curso_programado_origen_id
 */
class Asistencia extends Model
{
    /**
     * @var string
     */
    protected $table = 'asistencias';

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'curso_programado_id',
        'cliente_id',
        'fecha',
        'asistio',
        'anotaciones',
        'curso_programado_origen_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'curso_programado_id' => 'integer',
            'cliente_id' => 'integer',
            'fecha' => 'date',
            'asistio' => 'boolean',
            'curso_programado_origen_id' => 'integer',
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
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /**
     * @return BelongsTo<CursoProgramado, $this>
     */
    public function cursoProgramadoOrigen(): BelongsTo
    {
        return $this->belongsTo(CursoProgramado::class);
    }
}
