<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property int|null $capacity
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Classroom extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'capacity',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
        ];
    }

    /**
     * @return HasMany<CourseSchedule, $this>
     */
    public function courseSchedules(): HasMany
    {
        return $this->hasMany(CourseSchedule::class);
    }
}
