<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Enums\DocumentType;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property-read User|null $user
 * @property-read string|null $first_name
 * @property-read string|null $paternal_surname
 * @property-read string|null $maternal_surname
 * @property-read string|null $full_name
 * @property-read string|null $document
 * @property-read DocumentType|null $document_type
 * @property-read Carbon|null $birth_date
 * @property-read string|null $email
 * @property-read string|null $phone
 * @property-read string|null $username
 */
trait DelegatesToUser
{
    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getFirstNameAttribute(): ?string
    {
        return $this->user?->first_name;
    }

    public function getPaternalSurnameAttribute(): ?string
    {
        return $this->user?->paternal_surname;
    }

    public function getMaternalSurnameAttribute(): ?string
    {
        return $this->user?->maternal_surname;
    }

    public function getFullNameAttribute(): ?string
    {
        return $this->user?->full_name;
    }

    public function getDocumentAttribute(): ?string
    {
        return $this->user?->document;
    }

    public function getDocumentTypeAttribute(): ?DocumentType
    {
        return $this->user?->document_type;
    }

    public function getBirthDateAttribute(): ?Carbon
    {
        return $this->user?->birth_date;
    }

    public function getEmailAttribute(): ?string
    {
        return $this->user?->email;
    }

    public function getPhoneAttribute(): ?string
    {
        return $this->user?->phone;
    }

    public function getUsernameAttribute(): ?string
    {
        return $this->user?->username;
    }
}
