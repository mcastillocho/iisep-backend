<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DayOfWeek;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $scheduled_course_id
 * @property int|null $classroom_id
 * @property DayOfWeek $day_of_week
 * @property string $start_time
 * @property string $end_time
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CourseSchedule extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'scheduled_course_id',
        'classroom_id',
        'day_of_week',
        'start_time',
        'end_time',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_course_id' => 'integer',
            'classroom_id' => 'integer',
            'day_of_week' => DayOfWeek::class,
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
     * @return BelongsTo<Classroom, $this>
     */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }
}
