<?php

namespace Src\Identity\Infrastructure;

use Illuminate\Database\Eloquent\Relations\Relation;
use Src\Identity\Application\UserProfiles;
use Src\Identity\Domain\AccessTokens;
use Src\Identity\Domain\AccountNotifier;
use Src\Identity\Domain\PasswordResetCodes;
use Src\Identity\Domain\SocialIdentityVerifier;
use Src\Identity\Domain\UserRepository;
use Src\Identity\Infrastructure\Firebase\FirebaseSocialIdentityVerifier;
use Src\Identity\Infrastructure\Notifications\MailAccountNotifier;
use Src\Identity\Infrastructure\Persistence\DatabasePasswordResetCodes;
use Src\Identity\Infrastructure\Persistence\EloquentUserRepository;
use Src\Identity\Infrastructure\Persistence\SanctumAccessTokens;
use Src\Identity\Infrastructure\Persistence\UserModel;
use Src\Shared\Infrastructure\ContextServiceProvider;

class IdentityServiceProvider extends ContextServiceProvider
{
    public array $bindings = [
        UserRepository::class => EloquentUserRepository::class,
        AccessTokens::class => SanctumAccessTokens::class,
        PasswordResetCodes::class => DatabasePasswordResetCodes::class,
        AccountNotifier::class => MailAccountNotifier::class,
        SocialIdentityVerifier::class => FirebaseSocialIdentityVerifier::class,
        UserProfiles::class => TaggedUserProfiles::class,
    ];

    public function boot(): void
    {
        // Token Sanctum di DB bersama tersimpan dengan tokenable_type `App\Models\User` (aplikasi lama).
        Relation::morphMap(['App\\Models\\User' => UserModel::class]);

        parent::boot();
    }
}
