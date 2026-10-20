<?php

namespace Src\Identity\Application\EmailChange;

use Src\Identity\Application\Account\AccountNoticeSender;
use Src\Identity\Application\Auth\VerificationCodeGenerator;
use Src\Identity\Application\Auth\VerificationCodeHasher;
use Src\Identity\Domain\EmailChange;
use Src\Identity\Domain\EmailChangeRepository;
use Src\Identity\Domain\UserRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\MemberEmailDomains;
use Src\Shared\Application\Email\EmailSuppressions;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\TooManyAttempts;

/**
 * The signed-in account asks to change its sign-in email (`docs/design/phase-8-activation.md` section 7). The answer is the same whether or not the address belongs to another account: a free
 * address gets a six-digit code, an address in use gets only a notice that someone tried it. An account without a password (Google only) also gets a code at its current address, asked back
 * at the confirmation. One pending request per account; a new one replaces it.
 */
final readonly class RequestEmailChange
{
    public function __construct(
        private UserRepository $users,
        private EmailChangeRepository $changes,
        private EmailChangeThrottle $throttle,
        private EmailSuppressions $suppressions,
        private MemberEmailDomains $domains,
        private VerificationCodeGenerator $generator,
        private VerificationCodeHasher $hasher,
        private AccountNoticeSender $notices,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @throws EntityNotFound
     * @throws TooManyAttempts `email-change-limit-reached` (3 requests an hour) or `email-change-too-soon` (one change per 24 hours)
     * @throws BusinessRuleViolation `email-undeliverable` for an address on the suppression list, `email-domain-not-allowed` when the member's company limits its domains
     */
    public function execute(Actor $actor, string $email): void
    {
        $user = $this->users->findById((int) $actor->id) ?? throw EntityNotFound::of('User', $actor->id);
        $userId = (int) $user->id;
        $new = mb_strtolower(trim($email));

        $this->throttle->hitRequest($userId);
        $this->throttle->assertNotChangedRecently($userId);

        if ($new === $user->email()) {
            return;
        }

        if ($this->suppressions->reason($new) !== null) {
            throw new BusinessRuleViolation('This address does not receive email, so the code could not arrive. Use another address.', 'email-undeliverable');
        }

        $this->domains->assertAllowed($userId, $new);

        $holder = $this->users->findByEmail($new);

        if ($holder !== null) {
            $this->notices->emailChangeTaken($holder->email(), $holder->name(), $holder->locale());

            return;
        }

        $now = $this->clock->now();
        $newCode = $this->generator->generate();
        $oldCode = $user->passwordHash() === null ? $this->generator->generate() : null;
        $change = EmailChange::request($userId, $new, $this->hasher->make($newCode), $oldCode === null ? null : $this->hasher->make($oldCode), $now);

        $this->transactions->run(fn () => $this->changes->replace($change));

        $this->notices->emailChangeCode($new, $user->name(), $user->locale(), $new, $newCode, $change->expiresAt);

        if ($oldCode !== null) {
            $this->notices->emailChangeCode($user->email(), $user->name(), $user->locale(), $new, $oldCode, $change->expiresAt);
        }
    }
}
