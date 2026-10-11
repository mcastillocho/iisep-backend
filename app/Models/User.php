<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ClientType;
use App\Enums\DocumentType;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $first_name
 * @property string $last_name
 * @property string $document
 * @property DocumentType $document_type
 * @property Carbon|null $birth_date
 * @property string|null $email
 * @property string|null $phone
 * @property string $username
 * @property string $password
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read list<string> $roles
 * @property-read string $full_name
 * @property-read Client|null $client
 * @property-read Teacher|null $teacher
 * @property-read Staff|null $staff
 */
class User extends Authenticatable
{
    use Notifiable;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'document',
        'document_type',
        'birth_date',
        'email',
        'phone',
        'username',
        'password',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @var list<string>
     */
    protected $appends = [
        'roles',
        'full_name',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'document_type' => DocumentType::class,
            'password' => 'hashed',
        ];
    }

    /**
     * @return HasOne<Client, $this>
     */
    public function client(): HasOne
    {
        return $this->hasOne(Client::class);
    }

    /**
     * @return HasOne<Teacher, $this>
     */
    public function teacher(): HasOne
    {
        return $this->hasOne(Teacher::class);
    }

    /**
     * @return HasOne<Staff, $this>
     */
    public function staff(): HasOne
    {
        return $this->hasOne(Staff::class);
    }

    /**
     * @return list<string>
     */
    public function getRolesAttribute(): array
    {
        $roles = [];

        if ($this->client !== null) {
            $roles[] = 'client';
            $roles[] = $this->client->client_type->value;
            if ($this->client->client_type === ClientType::ESTUDIANTE) {
                $roles[] = 'student';
            }
        }

        if ($this->teacher !== null) {
            $roles[] = 'teacher';
            $roles[] = 'docente';
        }

        if ($this->staff !== null) {
            $roles[] = 'staff';
            $roles[] = 'administrativo';
        }

        return array_values(array_unique($roles));
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles, true);
    }

    public function isTeacher(): bool
    {
        return $this->teacher !== null;
    }

    public function isStaff(): bool
    {
        return $this->staff !== null;
    }

    public function isClient(): bool
    {
        return $this->client !== null;
    }

    public function isStudent(): bool
    {
        return $this->client?->client_type === ClientType::ESTUDIANTE;
    }
}
