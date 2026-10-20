<?php

namespace Src\Identity\Application\Users;

use Src\Identity\Application\Activation\SendActivationEmails;
use Src\Identity\Domain\AccountScope;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Audit\AuditAction;
use Src\Shared\Application\Audit\AuditActorType;
use Src\Shared\Application\Audit\AuditEntry;
use Src\Shared\Application\Audit\AuditTrail;
use Src\Shared\Application\Clock;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** A platform admin holding `users.manage` sends one imported, active account its activation email again (a support request). It is logged as a manual send and audited. */
final readonly class SendUserActivationEmail
{
    public function __construct(
        private ManagedAccounts $accounts,
        private SendActivationEmails $activation,
        private AuditTrail $audit,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied
     * @throws EntityNotFound
     * @throws StateConflict `account-not-active`, `account-not-imported` for an account that did not come from karir.pkp, `account-not-correctable` for a psychologist
     * @throws BusinessRuleViolation `activation-support-contact-missing` or `activation-url-missing` while the settings are not filled
     */
    public function execute(Actor $actor, int $userId, ?string $ipAddress, ?string $userAgent): void
    {
        $user = $this->accounts->find($actor, $userId);
        $this->accounts->assertActive($user);

        if ($user->importedAt() === null) {
            throw new StateConflict('Only an account imported from karir.pkp gets an activation email.', 'account-not-imported');
        }

        $this->activation->sendOne($user);

        $this->audit->record(new AuditEntry(
            AuditActorType::Admin, (int) $actor->id, AuditAction::ACCOUNT_ACTIVATION_EMAIL_SENT, AccountScope::User->value, $userId, [], $ipAddress, $userAgent, $this->clock->now(),
        ));
    }
}
