<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EnrollmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $scheduled_course_id
 * @property int $student_id
 * @property int|null $student_career_id
 * @property int|null $guardian_id
 * @property Carbon $enrollment_date
 * @property EnrollmentStatus $status
 */
class Enrollment extends Model
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'scheduled_course_id',
        'student_id',
        'student_career_id',
        'guardian_id',
        'enrollment_date',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_course_id' => 'integer',
            'student_id' => 'integer',
            'student_career_id' => 'integer',
            'guardian_id' => 'integer',
            'enrollment_date' => 'date',
            'status' => EnrollmentStatus::class,
        ];
    }

    /**
     * @return BelongsTo<ScheduledCourse, $this>
     */
    public function scheduledCourse(): BelongsTo
    {
        return $this->belongsTo(ScheduledCourse::class);
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'student_id');
    }

    /**
     * @return BelongsTo<StudentCareer, $this>
     */
    public function studentCareer(): BelongsTo
    {
        return $this->belongsTo(StudentCareer::class, 'student_career_id');
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'guardian_id');
    }
}
