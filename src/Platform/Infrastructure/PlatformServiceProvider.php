<?php

namespace Src\Platform\Infrastructure;

use Illuminate\Support\Facades\Event;
use Src\Identity\Domain\Events\AccountDeletionCancelled;
use Src\Identity\Domain\Events\AccountDeletionRequested;
use Src\Identity\Domain\Events\PasswordReset;
use Src\Identity\Domain\Events\SignedIn;
use Src\Identity\Domain\Events\SignInFailed;
use Src\Identity\Domain\Events\UserAnonymized;
use Src\Platform\Application\Audit\AuditLogRepository;
use Src\Platform\Infrastructure\Listeners\RecordIdentityAuditEntries;
use Src\Platform\Infrastructure\Persistence\DatabaseAuditLogRepository;
use Src\Shared\Infrastructure\ContextServiceProvider;

final class PlatformServiceProvider extends ContextServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        AuditLogRepository::class => DatabaseAuditLogRepository::class,
    ];

    public function boot(): void
    {
        foreach ([
            SignedIn::class => 'onSignedIn',
            SignInFailed::class => 'onSignInFailed',
            PasswordReset::class => 'onPasswordReset',
            AccountDeletionRequested::class => 'onAccountDeletionRequested',
            AccountDeletionCancelled::class => 'onAccountDeletionCancelled',
            UserAnonymized::class => 'onUserAnonymized',
        ] as $event => $method) {
            Event::listen($event, [RecordIdentityAuditEntries::class, $method]);
        }

        parent::boot();
    }
}
