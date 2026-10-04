<?php

namespace Src\Identity\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Eloquent record of `identity_verification_codes` (only `created_at`, no `updated_at`).
 *
 * @property int $id
 * @property string $subject_type
 * @property int $subject_id
 * @property string $purpose
 * @property string $code_hash
 * @property Carbon $expires_at
 * @property Carbon|null $consumed_at
 * @property int $attempts
 * @property Carbon $created_at
 */
final class VerificationCodeModel extends Model
{
    public $timestamps = false;

    protected $table = 'identity_verification_codes';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_datetime',
            'consumed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
