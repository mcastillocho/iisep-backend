<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TipoCliente;
use App\Enums\TipoDocumento;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $nombre
 * @property string $apellido
 * @property Carbon|null $fecha_nacimiento
 * @property TipoCliente $tipo_cliente
 * @property string $documento
 * @property TipoDocumento $tipo_documento
 * @property string|null $email
 * @property string|null $telefono
 */
class Cliente extends Model
{
    /**
     * @var string
     */
    protected $table = 'clientes';

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
        'fecha_nacimiento',
        'tipo_cliente',
        'documento',
        'tipo_documento',
        'email',
        'telefono',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_nacimiento' => 'date',
            'tipo_cliente' => TipoCliente::class,
            'tipo_documento' => TipoDocumento::class,
        ];
    }

    /**
     * @return HasOne<Credencial, $this>
     */
    public function credencial(): HasOne
    {
        return $this->hasOne(Credencial::class);
    }

    /**
     * @return HasMany<EstudianteCarrera, $this>
     */
    public function estudiantesCarreras(): HasMany
    {
        return $this->hasMany(EstudianteCarrera::class, 'estudiante_id');
    }

    /**
     * @return BelongsToMany<Carrera, $this>
     */
    public function carreras(): BelongsToMany
    {
        return $this->belongsToMany(Carrera::class, 'estudiantes_carreras', 'estudiante_id', 'carrera_id')
            ->withPivot(['id', 'fecha_ingreso', 'estado']);
    }

    /**
     * @return HasMany<Matricula, $this>
     */
    public function matriculas(): HasMany
    {
        return $this->hasMany(Matricula::class, 'estudiante_id');
    }

    /**
     * @return HasMany<Matricula, $this>
     */
    public function matriculasComoApoderado(): HasMany
    {
        return $this->hasMany(Matricula::class, 'apoderado_id');
    }

    /**
     * @return HasMany<Asistencia, $this>
     */
    public function asistencias(): HasMany
    {
        return $this->hasMany(Asistencia::class);
    }
}
