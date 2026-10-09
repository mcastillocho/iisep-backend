<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $cliente_id
 * @property string $username
 * @property string $password_hash
 * @property Carbon $fecha_creacion
 */
class Credencial extends Authenticatable
{
    use Notifiable;

    /**
     * @var string
     */
    protected $table = 'credenciales';

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'cliente_id',
        'username',
        'password_hash',
        'fecha_creacion',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password_hash',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cliente_id' => 'integer',
            'fecha_creacion' => 'datetime',
        ];
    }

    /**
     * Get the name of the password attribute for the authenticatable model.
     */
    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    /**
     * @return BelongsTo<Cliente, $this>
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }
}
