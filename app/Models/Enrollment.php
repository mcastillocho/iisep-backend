<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EnrollmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $scheduled_course_id
 * @property int $student_id
 * @property int|null $student_career_id
 * @property int|null $module_id
 * @property int|null $guardian_id
 * @property Carbon $enrollment_date
 * @property EnrollmentStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read ScheduledCourse $scheduledCourse
 * @property-read Student $student
 * @property-read StudentCareer|null $studentCareer
 * @property-read Module|null $module
 * @property-read User|null $guardian
 */
class Enrollment extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'scheduled_course_id',
        'student_id',
        'student_career_id',
        'module_id',
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
            'module_id' => 'integer',
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
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<StudentCareer, $this>
     */
    public function studentCareer(): BelongsTo
    {
        return $this->belongsTo(StudentCareer::class);
    }

    /**
     * @return BelongsTo<Module, $this>
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function guardian(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
