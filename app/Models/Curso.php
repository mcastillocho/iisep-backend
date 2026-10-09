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
class Curso extends Model
{
    /**
     * @var string
     */
    protected $table = 'cursos';

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
     * @return HasMany<ModuloCurso, $this>
     */
    public function modulosCursos(): HasMany
    {
        return $this->hasMany(ModuloCurso::class);
    }

    /**
     * @return BelongsToMany<Modulo, $this>
     */
    public function modulos(): BelongsToMany
    {
        return $this->belongsToMany(Modulo::class, 'modulos_cursos')
            ->withPivot(['id']);
    }

    /**
     * @return HasMany<CursoProgramado, $this>
     */
    public function cursosProgramados(): HasMany
    {
        return $this->hasMany(CursoProgramado::class);
    }
}
