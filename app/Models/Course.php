<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 */
class Course extends Model
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'description',
    ];

    /**
     * @return BelongsToMany<Module, $this>
     */
    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class)
            ->withPivot(['id']);
    }

    /**
     * @return HasMany<ScheduledCourse, $this>
     */
    public function scheduledCourses(): HasMany
    {
        return $this->hasMany(ScheduledCourse::class);
    }
}
