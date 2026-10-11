<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DocumentType;
use App\Models\Concerns\DelegatesToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $specialty
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User|null $user
 * @property-read string|null $first_name
 * @property-read string|null $last_name
 * @property-read string|null $full_name
 * @property-read string|null $document
 * @property-read DocumentType|null $document_type
 * @property-read Carbon|null $birth_date
 * @property-read string|null $email
 * @property-read string|null $phone
 * @property-read string|null $username
 */
class Teacher extends Model
{
    use DelegatesToUser;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'specialty',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
        ];
    }

    /**
     * @return HasMany<ScheduledCourse, $this>
     */
    public function scheduledCourses(): HasMany
    {
        return $this->hasMany(ScheduledCourse::class);
    }
}
