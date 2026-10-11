<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $scheduled_course_id
 * @property int $student_id
 * @property Carbon $date
 * @property bool $attended
 * @property string|null $notes
 * @property int|null $origin_scheduled_course_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read ScheduledCourse $scheduledCourse
 * @property-read Student $student
 * @property-read ScheduledCourse|null $originScheduledCourse
 */
class Attendance extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'scheduled_course_id',
        'student_id',
        'date',
        'attended',
        'notes',
        'origin_scheduled_course_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_course_id' => 'integer',
            'student_id' => 'integer',
            'date' => 'date',
            'attended' => 'boolean',
            'origin_scheduled_course_id' => 'integer',
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
     * @return BelongsTo<ScheduledCourse, $this>
     */
    public function originScheduledCourse(): BelongsTo
    {
        return $this->belongsTo(ScheduledCourse::class);
    }
}
