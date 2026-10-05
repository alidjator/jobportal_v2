<?php

namespace Src\Identity\Application\Account;

use RuntimeException;
use Src\Identity\Application\Auth\AccessTokenIssuer;
use Src\Identity\Application\Auth\PasswordHasher;
use Src\Identity\Application\Auth\SignInUser;
use Src\Identity\Domain\AccountScope;
use Src\Identity\Domain\Events\SignedIn;
use Src\Identity\Domain\Events\UserRegistered;
use Src\Identity\Domain\User;
use Src\Identity\Domain\UserRepository;
use Src\Shared\Application\Accounts\AccountSession;
use Src\Shared\Application\Accounts\CompanyMemberAccounts;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;

/** Identity's side of the Company context's needs: company-member accounts are created and signed in here, nowhere else. */
final readonly class IdentityCompanyMemberAccounts implements CompanyMemberAccounts
{
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $hasher,
        private AccessTokenIssuer $tokens,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    public function emailIsRegistered(string $email): bool
    {
        return $this->users->findByEmail(mb_strtolower(trim($email))) !== null;
    }

    public function register(string $name, string $email, string $password, string $locale): int
    {
        $now = $this->clock->now();
        $user = $this->users->save(User::createCompanyMember(
            $name, mb_strtolower(trim($email)), $this->hasher->make($password), $locale, $now,
        ));

        $this->events->dispatch([new UserRegistered((int) $user->id, $user->type->value, $now)]);

        return (int) $user->id;
    }

    public function signIn(int $userId, string $deviceName, ?string $ipAddress, ?string $userAgent): AccountSession
    {
        $user = $this->users->findById($userId) ?? throw new RuntimeException("Account {$userId} does not exist.");
        $now = $this->clock->now();
        $expiresAt = $now->modify(SignInUser::TOKEN_LIFETIME);

        $user->recordSignIn($now);
        $user = $this->users->save($user);
        $token = $this->tokens->issueForUser($userId, $deviceName, $expiresAt);

        $this->events->dispatch([new SignedIn(AccountScope::User, $userId, $ipAddress, $userAgent, $now)]);

        return new AccountSession(
            $token, $expiresAt, $userId, $user->type->value, $user->name(), $user->email(),
            $user->isEmailVerified(), $user->status()->value, $user->locale(),
        );
    }
}
