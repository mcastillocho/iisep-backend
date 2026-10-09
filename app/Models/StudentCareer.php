<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StudentCareerStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $student_id
 * @property int $career_id
 * @property Carbon $admission_date
 * @property StudentCareerStatus $status
 */
class StudentCareer extends Model
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'student_id',
        'career_id',
        'admission_date',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'student_id' => 'integer',
            'career_id' => 'integer',
            'admission_date' => 'date',
            'status' => StudentCareerStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'student_id');
    }

    /**
     * @return BelongsTo<Career, $this>
     */
    public function career(): BelongsTo
    {
        return $this->belongsTo(Career::class, 'career_id');
    }

    /**
     * @return HasMany<Enrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'student_career_id');
    }
}
