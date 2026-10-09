<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $nombre
 * @property string $apellido
 * @property string|null $email
 * @property string|null $telefono
 */
class Profesor extends Model
{
    /**
     * @var string
     */
    protected $table = 'profesores';

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nombre',
        'apellido',
        'email',
        'telefono',
    ];

    /**
     * @return HasMany<CursoProgramado, $this>
     */
    public function cursosProgramados(): HasMany
    {
        return $this->hasMany(CursoProgramado::class, 'profesor_id');
    }
}
