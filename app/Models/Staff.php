<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DocumentType;
use App\Models\Concerns\DelegatesToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $branch_id
 * @property string|null $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User|null $user
 * @property-read Branch|null $branch
 * @property-read string|null $first_name
 * @property-read string|null $paternal_surname
 * @property-read string|null $maternal_surname
 * @property-read string|null $full_name
 * @property-read string|null $document
 * @property-read DocumentType|null $document_type
 * @property-read Carbon|null $birth_date
 * @property-read string|null $email
 * @property-read string|null $phone
 * @property-read string|null $username
 */
class Staff extends Model
{
    use DelegatesToUser;
    use SoftDeletes;

    /**
     * @var string
     */
    protected $table = 'staff';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'branch_id',
        'position',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'branch_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Determina si el personal es Administrador General (no tiene sede asignada / branch_id es null).
     */
    public function isAdmin(): bool
    {
        return $this->branch_id === null;
    }

    /**
     * Determina si el personal es Coordinador de Sede (cargo 'coord' y sede asignada).
     * Opcionalmente valida contra un id de sede específico ($branchId).
     */
    public function isCoordinador(?int $branchId = null): bool
    {
        if ($this->branch_id === null || $this->position !== 'coord') {
            return false;
        }

        return $branchId === null || $this->branch_id === $branchId;
    }

    /**
     * Alias de isCoordinador.
     */
    public function isCoord(?int $branchId = null): bool
    {
        return $this->isCoordinador($branchId);
    }

    /**
     * Determina si el personal es Secretaría de Sede (cargo 'secre' y sede asignada).
     * Opcionalmente valida contra un id de sede específico ($branchId).
     */
    public function isSecretaria(?int $branchId = null): bool
    {
        if ($this->branch_id === null || $this->position !== 'secre') {
            return false;
        }

        return $branchId === null || $this->branch_id === $branchId;
    }

    /**
     * Alias de isSecretaria.
     */
    public function isSecre(?int $branchId = null): bool
    {
        return $this->isSecretaria($branchId);
    }
}
