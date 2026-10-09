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
class Career extends Model
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
        return $this->hasMany(CareerModule::class, 'career_id');
    }

    /**
     * @return BelongsToMany<Module, $this>
     */
    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'career_modules', 'career_id', 'module_id')
            ->withPivot(['id', 'order'])
            ->orderByPivot('order');
    }

    /**
     * @return HasMany<StudentCareer, $this>
     */
    public function studentCareers(): HasMany
    {
        return $this->hasMany(StudentCareer::class, 'career_id');
    }

    /**
     * @return BelongsToMany<Client, $this>
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Client::class, 'student_careers', 'career_id', 'student_id')
            ->withPivot(['id', 'admission_date', 'status']);
    }
}
