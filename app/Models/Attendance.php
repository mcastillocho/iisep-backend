<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $scheduled_course_id
 * @property int $client_id
 * @property Carbon $date
 * @property bool $attended
 * @property string|null $notes
 * @property int|null $origin_scheduled_course_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Attendance extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'scheduled_course_id',
        'client_id',
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
            'client_id' => 'integer',
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
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<ScheduledCourse, $this>
     */
    public function originScheduledCourse(): BelongsTo
    {
        return $this->belongsTo(ScheduledCourse::class);
    }
}
