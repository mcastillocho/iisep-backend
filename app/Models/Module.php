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
class Module extends Model
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
     * @return HasMany<CareerModule, $this>
     */
    public function careerModules(): HasMany
    {
        return $this->hasMany(CareerModule::class, 'module_id');
    }

    /**
     * @return BelongsToMany<Career, $this>
     */
    public function careers(): BelongsToMany
    {
        return $this->belongsToMany(Career::class, 'career_modules', 'module_id', 'career_id')
            ->withPivot(['id', 'order']);
    }

    /**
     * @return HasMany<ModuleCourse, $this>
     */
    public function moduleCourses(): HasMany
    {
        return $this->hasMany(ModuleCourse::class, 'module_id');
    }

    /**
     * @return BelongsToMany<Course, $this>
     */
    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'module_courses', 'module_id', 'course_id')
            ->withPivot(['id']);
    }
}
