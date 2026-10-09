<?php

namespace Src\Identity\Application\Auth;

use Src\Identity\Domain\AccountScope;
use Src\Identity\Domain\Events\SignedIn;
use Src\Identity\Domain\Events\UserRegistered;
use Src\Identity\Domain\User;
use Src\Identity\Domain\UserRepository;
use Src\Identity\Domain\VerificationPurpose;
use Src\Shared\Application\Campaign\CampaignCodes;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\Shared\Domain\Exceptions\TooManyAttempts;

/**
 * Registers a job seeker, signs them in (30-day token) and emails a verification code. The account can be used right
 * away; actions that affect other parties wait for the verified email. Terms acceptance is handled by the terms gate.
 */
final readonly class RegisterJobSeeker
{
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $hasher,
        private AccessTokenIssuer $tokens,
        private RegistrationThrottle $throttle,
        private VerificationCodeIssuer $codes,
        private VerificationCodeSender $sender,
        private CampaignCodes $campaigns,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws TooManyAttempts when the client address or the email address has used up its registration attempts
     * @throws StateConflict when the email is already registered
     */
    public function execute(RegisterJobSeekerCommand $command): UserSignInResult
    {
        $email = mb_strtolower(trim($command->email));

        $this->throttle->hit($command->ipAddress, $email);

        if ($this->users->findByEmail($email) !== null) {
            throw new StateConflict('An account with this email already exists.', 'email-already-registered');
        }

        $now = $this->clock->now();
        $expiresAt = $now->modify(SignInUser::TOKEN_LIFETIME);
        $user = User::registerJobSeeker($command->name, $email, $this->hasher->make($command->password), $command->language, $this->campaigns->activeCode($command->campaignCode));
        $user->recordSignIn($now);

        [$user, $token, $issued] = $this->transactions->run(function () use ($user, $command, $expiresAt): array {
            $user = $this->users->save($user);

            return [
                $user,
                $this->tokens->issueForUser((int) $user->id, $command->deviceName, $expiresAt),
                $this->codes->issueForUser((int) $user->id, VerificationPurpose::EmailVerification),
            ];
        });

        $this->sender->sendEmailVerification($user->email(), $user->name(), $user->locale(), $issued->code, $issued->expiresAt);
        $this->events->dispatch([
            new UserRegistered((int) $user->id, $user->type->value, $now),
            new SignedIn(AccountScope::User, (int) $user->id, $command->ipAddress, $command->userAgent, $now),
        ]);

        return new UserSignInResult($token, $expiresAt, $user);
    }
}
