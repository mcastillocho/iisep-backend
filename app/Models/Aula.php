<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $nombre
 * @property int|null $capacidad
 */
class Aula extends Model
{
    /**
     * @var string
     */
    protected $table = 'aulas';

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nombre',
        'capacidad',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'capacidad' => 'integer',
        ];
    }

    /**
     * @return HasMany<HorarioCurso, $this>
     */
    public function horariosCursos(): HasMany
    {
        return $this->hasMany(HorarioCurso::class, 'aula_id');
    }
}
