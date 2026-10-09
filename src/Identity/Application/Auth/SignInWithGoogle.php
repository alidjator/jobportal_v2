<?php

namespace Src\Identity\Application\Auth;

use Src\Identity\Domain\AccountScope;
use Src\Identity\Domain\Events\SignedIn;
use Src\Identity\Domain\Events\SignInFailed;
use Src\Identity\Domain\Events\UserRegistered;
use Src\Identity\Domain\SocialAccountRepository;
use Src\Identity\Domain\User;
use Src\Identity\Domain\UserRepository;
use Src\Shared\Application\Campaign\CampaignCodes;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\AuthenticationFailed;
use Src\Shared\Domain\Exceptions\ExternalServiceUnavailable;
use Src\Shared\Domain\Exceptions\TooManyAttempts;

/**
 * Signs a user in with a Google ID token (verified by the API itself, no Firebase), creating the account on the first
 * sign-in. The Google account is identified by its stable subject, never by the email alone. When an account with the same
 * email already exists it is linked, but only because Google vouches for the email: if that account's email was never verified,
 * its password is removed (whoever registered it may not own the address) and its tokens are revoked.
 */
final readonly class SignInWithGoogle
{
    public function __construct(
        private GoogleIdentityVerifier $google,
        private UserRepository $users,
        private SocialAccountRepository $socialAccounts,
        private AccessTokenIssuer $tokens,
        private SignInThrottle $throttle,
        private CampaignCodes $campaigns,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws TooManyAttempts after too many invalid tokens from one address
     * @throws AuthenticationFailed `invalid-google-token`, or `google-email-not-verified` when Google has not verified the email
     * @throws ExternalServiceUnavailable
     * @throws AccessDenied when the account is suspended or deactivated
     */
    public function execute(GoogleSignInCommand $command): GoogleSignInResult
    {
        $throttleKey = 'google|'.$command->ipAddress;
        $this->throttle->assertNotLocked($throttleKey);

        try {
            $identity = $this->google->verify($command->idToken);

            if (! $identity->emailVerified) {
                throw new AuthenticationFailed('Google has not verified this email address.', 'google-email-not-verified');
            }
        } catch (AuthenticationFailed $e) {
            $this->throttle->recordFailure($throttleKey);
            $this->events->dispatch([$this->failure(null, $e->errorCode(), $command)]);

            throw $e;
        }

        $email = mb_strtolower(trim($identity->email));
        $linkedUserId = $this->socialAccounts->findUserId(SocialAccountRepository::GOOGLE, $identity->subject);
        $user = $linkedUserId !== null ? $this->users->findById($linkedUserId) : $this->users->findByEmail($email);
        $isNewUser = $user === null;

        if ($user !== null) {
            try {
                $user->assertCanSignIn();
            } catch (AccessDenied $e) {
                $this->events->dispatch([$this->failure($user->id, $e->errorCode(), $command)]);

                throw $e;
            }
        }

        $now = $this->clock->now();
        $expiresAt = $now->modify(SignInUser::TOKEN_LIFETIME);

        [$user, $token] = $this->transactions->run(function () use ($user, $identity, $email, $linkedUserId, $command, $now, $expiresAt): array {
            $user ??= User::registerWithGoogle($this->nameOf($identity, $email), $email, $command->language, $this->campaigns->activeCode($command->campaignCode), $now);
            $passwordRemoved = $linkedUserId === null && $user->id !== null && $user->confirmEmailOwnershipThroughGoogle($now);

            $user->recordSignIn($now);
            $user = $this->users->save($user);

            if ($linkedUserId === null) {
                $this->socialAccounts->link((int) $user->id, SocialAccountRepository::GOOGLE, $identity->subject, $email);
            }

            if ($passwordRemoved) {
                $this->tokens->revokeAllForUser((int) $user->id);
            }

            return [$user, $this->tokens->issueForUser((int) $user->id, $command->deviceName, $expiresAt)];
        });

        $this->throttle->clear($throttleKey);
        $this->events->dispatch([
            ...($isNewUser ? [new UserRegistered((int) $user->id, $user->type->value, $now)] : []),
            new SignedIn(AccountScope::User, (int) $user->id, $command->ipAddress, $command->userAgent, $now),
        ]);

        return new GoogleSignInResult(new UserSignInResult($token, $expiresAt, $user), $isNewUser);
    }

    private function nameOf(GoogleIdentity $identity, string $email): string
    {
        $name = trim($identity->name ?? '');

        return mb_substr($name !== '' ? $name : explode('@', $email)[0], 0, 255);
    }

    private function failure(?int $accountId, string $reason, GoogleSignInCommand $command): SignInFailed
    {
        return new SignInFailed(AccountScope::User, $accountId, $reason, $command->ipAddress, $command->userAgent, $this->clock->now());
    }
}
