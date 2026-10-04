<?php

namespace Src\Identity\Infrastructure\Persistence\Eloquent;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Contracts\HasApiTokens as HasApiTokensContract;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorProvider;

/**
 * Eloquent record of `identity_admins`. It is the authenticatable behind the `admin` guard and carries the roles and
 * permissions (Spatie). It never leaves the Infrastructure layer.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property Carbon|null $email_verified_at
 * @property string $status
 * @property string|null $avatar_path
 * @property Carbon|null $last_login_at
 */
final class AdminModel extends Authenticatable implements ActorProvider, HasApiTokensContract
{
    use HasApiTokens;
    use HasRoles;

    public const string GUARD = 'admin';

    protected $table = 'identity_admins';

    protected $guarded = [];

    protected $hidden = ['password'];

    protected string $guard_name = self::GUARD;

    public function toActor(): Actor
    {
        return Actor::admin($this->id);
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'immutable_datetime',
            'last_login_at' => 'immutable_datetime',
        ];
    }
}
