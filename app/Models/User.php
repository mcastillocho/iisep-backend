<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DocumentType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $first_name
 * @property string $paternal_surname
 * @property string|null $maternal_surname
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
 * @property-read Student|null $student
 * @property-read Teacher|null $teacher
 * @property-read Staff|null $staff
 * @property-read Collection<int, Enrollment> $guardianEnrollments
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
        'paternal_surname',
        'maternal_surname',
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
     * @return HasOne<Student, $this>
     */
    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
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
     * @return HasMany<Enrollment, $this>
     */
    public function guardianEnrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'guardian_id');
    }

    /**
     * @return HasMany<Student, $this>
     */
    public function studentsAsGuardian(): HasMany
    {
        return $this->hasMany(Student::class, 'guardian_id');
    }

    /**
     * @return list<string>
     */
    public function getRolesAttribute(): array
    {
        $roles = [];

        if ($this->student !== null) {
            $roles[] = 'student';
            $roles[] = 'estudiante';
        }

        if ($this->teacher !== null) {
            $roles[] = 'teacher';
            $roles[] = 'docente';
        }

        if ($this->staff !== null) {
            $roles[] = 'staff';
            $roles[] = 'administrativo';
        }

        $hasGuardianLinks = ($this->relationLoaded('guardianEnrollments') ? $this->guardianEnrollments->isNotEmpty() : $this->guardianEnrollments()->exists())
            || ($this->relationLoaded('studentsAsGuardian') ? $this->studentsAsGuardian->isNotEmpty() : $this->studentsAsGuardian()->exists());

        if ($hasGuardianLinks) {
            $roles[] = 'guardian';
            $roles[] = 'apoderado';
        }

        return array_values(array_unique($roles));
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->paternal_surname} ".($this->maternal_surname ?? ''));
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles, true);
    }

    public function isStudent(): bool
    {
        return $this->student !== null;
    }

    public function isTeacher(): bool
    {
        return $this->teacher !== null;
    }

    public function isStaff(): bool
    {
        return $this->staff !== null;
    }

    public function isGuardian(): bool
    {
        $hasGuardianEnrollments = $this->relationLoaded('guardianEnrollments')
            ? $this->guardianEnrollments->isNotEmpty()
            : $this->guardianEnrollments()->exists();

        $hasStudents = $this->relationLoaded('studentsAsGuardian')
            ? $this->studentsAsGuardian->isNotEmpty()
            : $this->studentsAsGuardian()->exists();

        return $hasGuardianEnrollments || $hasStudents;
    }
}
