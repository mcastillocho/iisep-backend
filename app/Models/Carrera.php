<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $nombre
 * @property string|null $descripcion
 */
class Carrera extends Model
{
    /**
     * @var string
     */
    protected $table = 'carreras';

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nombre',
        'descripcion',
    ];

    /**
     * @return HasMany<CarreraModulo, $this>
     */
    public function carrerasModulos(): HasMany
    {
        return $this->hasMany(CarreraModulo::class);
    }

    /**
     * @return BelongsToMany<Modulo, $this>
     */
    public function modulos(): BelongsToMany
    {
        return $this->belongsToMany(Modulo::class, 'carreras_modulos')
            ->withPivot(['id', 'orden'])
            ->orderByPivot('orden');
    }

    /**
     * @return HasMany<EstudianteCarrera, $this>
     */
    public function estudiantesCarreras(): HasMany
    {
        return $this->hasMany(EstudianteCarrera::class);
    }

    /**
     * @return BelongsToMany<Cliente, $this>
     */
    public function estudiantes(): BelongsToMany
    {
        return $this->belongsToMany(Cliente::class, 'estudiantes_carreras', 'carrera_id', 'estudiante_id')
            ->withPivot(['id', 'fecha_ingreso', 'estado']);
    }
}
