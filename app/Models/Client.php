<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ClientType;
use App\Enums\DocumentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $first_name
 * @property string $last_name
 * @property Carbon|null $birth_date
 * @property ClientType $client_type
 * @property string $document
 * @property DocumentType $document_type
 * @property string|null $email
 * @property string|null $phone
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Client extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'birth_date',
        'client_type',
        'document',
        'document_type',
        'email',
        'phone',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'client_type' => ClientType::class,
            'document_type' => DocumentType::class,
        ];
    }

    /**
     * @return HasOne<Credential, $this>
     */
    public function credential(): HasOne
    {
        return $this->hasOne(Credential::class);
    }

    /**
     * @return HasMany<StudentCareer, $this>
     */
    public function studentCareers(): HasMany
    {
        return $this->hasMany(StudentCareer::class, 'student_id');
    }

    /**
     * @return BelongsToMany<Career, $this>
     */
    public function careers(): BelongsToMany
    {
        return $this->belongsToMany(Career::class, 'student_careers', 'student_id', 'career_id')
            ->withPivot(['id', 'admission_date', 'status'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<Enrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'student_id');
    }

    /**
     * @return HasMany<Enrollment, $this>
     */
    public function guardianEnrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'guardian_id');
    }

    /**
     * @return HasMany<Attendance, $this>
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }
}
