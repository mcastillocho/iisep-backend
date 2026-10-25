<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $module_id
 * @property string $name
 * @property string|null $description
 * @property int|null $duration_in_weeks
 * @property bool $is_workshop
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Module|null $module
 */
class Course extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'module_id',
        'name',
        'description',
        'duration_in_weeks',
        'is_workshop',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'module_id' => 'integer',
            'duration_in_weeks' => 'integer',
            'is_workshop' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Module, $this>
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * @return HasMany<ScheduledCourse, $this>
     */
    public function scheduledCourses(): HasMany
    {
        return $this->hasMany(ScheduledCourse::class);
    }
}
