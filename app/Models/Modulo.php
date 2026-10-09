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
class Modulo extends Model
{
    /**
     * @var string
     */
    protected $table = 'modulos';

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
     * @return BelongsToMany<Carrera, $this>
     */
    public function carreras(): BelongsToMany
    {
        return $this->belongsToMany(Carrera::class, 'carreras_modulos')
            ->withPivot(['id', 'orden']);
    }

    /**
     * @return HasMany<ModuloCurso, $this>
     */
    public function modulosCursos(): HasMany
    {
        return $this->hasMany(ModuloCurso::class);
    }

    /**
     * @return BelongsToMany<Curso, $this>
     */
    public function cursos(): BelongsToMany
    {
        return $this->belongsToMany(Curso::class, 'modulos_cursos')
            ->withPivot(['id']);
    }
}
