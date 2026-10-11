<?php

namespace Src\Identity\Infrastructure\Persistence\Eloquent;

use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Contracts\HasApiTokens as HasApiTokensContract;
use Laravel\Sanctum\HasApiTokens;
use Src\Identity\Domain\UserType;
use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorProvider;

/**
 * Eloquent record of `identity_users`. It is the authenticatable behind the `user` guard and never leaves the
 * Infrastructure layer: use cases work with the `Src\Identity\Domain\User` aggregate.
 *
 * @property int $id
 * @property string $type
 * @property string $name
 * @property string $email
 * @property string|null $password
 * @property Carbon|null $email_verified_at
 * @property string $status
 * @property string|null $avatar_path
 * @property string $locale
 * @property string|null $registration_campaign_code
 * @property Carbon|null $last_login_at
 * @property Carbon|null $deletion_requested_at
 * @property Carbon|null $anonymized_at
 */
final class UserModel extends Authenticatable implements ActorProvider, HasApiTokensContract, HasLocalePreference
{
    use HasApiTokens;

    protected $table = 'identity_users';

    /** Used for the request language and for emails sent to this user. */
    public function preferredLocale(): string
    {
        return $this->locale;
    }

    public function toActor(): Actor
    {
        return match (UserType::from($this->type)) {
            UserType::CompanyMember => Actor::companyMember($this->id),
            UserType::Psychologist => Actor::psychologist($this->id),
            UserType::JobSeeker => Actor::jobSeeker($this->id),
        };
    }

    protected $guarded = [];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'immutable_datetime',
            'last_login_at' => 'immutable_datetime',
            'deletion_requested_at' => 'immutable_datetime',
            'anonymized_at' => 'immutable_datetime',
        ];
    }
}
