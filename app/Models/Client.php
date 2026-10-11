<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ClientType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property ClientType $client_type
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User $user
 */
class Client extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'client_type',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'client_type' => ClientType::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
