<?php

namespace Src\Identity\Application\Account;

use RuntimeException;
use Src\Identity\Application\Auth\AccessTokenIssuer;
use Src\Identity\Application\Auth\MemberTokenLifetime;
use Src\Identity\Application\Auth\PasswordHasher;
use Src\Identity\Application\TwoFactor\MemberTwoFactorGate;
use Src\Identity\Domain\AccountScope;
use Src\Identity\Domain\Events\SignedIn;
use Src\Identity\Domain\Events\UserRegistered;
use Src\Identity\Domain\User;
use Src\Identity\Domain\UserRepository;
use Src\Shared\Application\Accounts\AccountAvatar;
use Src\Shared\Application\Accounts\AccountSession;
use Src\Shared\Application\Accounts\CompanyMemberAccounts;
use Src\Shared\Application\Accounts\MemberAccountSummary;
use Src\Shared\Application\Accounts\TwoFactorChallengeRequired;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\FileStorage;

/** Identity's side of the Company context's needs: company-member accounts are created and signed in here, nowhere else. */
final readonly class IdentityCompanyMemberAccounts implements CompanyMemberAccounts
{
    private const array MIME_TYPES = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];

    public function __construct(
        private UserRepository $users,
        private PasswordHasher $hasher,
        private AccessTokenIssuer $tokens,
        private MemberTwoFactorGate $twoFactor,
        private MemberTokenLifetime $lifetime,
        private FileStorage $files,
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

    public function deactivate(int $userId): void
    {
        $user = $this->users->findById($userId) ?? throw new RuntimeException("Account {$userId} does not exist.");

        $user->deactivate();
        $this->users->save($user);
        $this->tokens->revokeAllForUser($userId);
    }

    public function avatar(int $userId): ?AccountAvatar
    {
        $path = $this->users->findById($userId)?->avatarPath();
        $contents = $path === null ? null : $this->files->get($path);

        return $path === null || $contents === null ? null : new AccountAvatar($contents, self::MIME_TYPES[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? 'application/octet-stream');
    }

    public function rename(int $userId, string $name): void
    {
        $user = $this->users->findById($userId) ?? throw new RuntimeException("Account {$userId} does not exist.");

        $user->rename($name);
        $this->users->save($user);
    }

    public function passwordMatches(int $userId, string $password): bool
    {
        return $this->hasher->check($password, $this->users->findById($userId)?->passwordHash());
    }

    public function revokeSessions(int $userId): void
    {
        $this->tokens->revokeAllForUser($userId);
    }

    public function summaries(array $userIds): array
    {
        $summaries = [];

        foreach ($this->users->findByIds($userIds) as $id => $user) {
            $summaries[$id] = new MemberAccountSummary($id, $user->name(), $user->email(), $user->status()->value, $user->lastLoginAt(), $user->locale(), $user->avatarPath() !== null);
        }

        return $summaries;
    }

    public function signIn(int $userId, string $deviceName, ?string $ipAddress, ?string $userAgent): AccountSession|TwoFactorChallengeRequired
    {
        $user = $this->users->findById($userId) ?? throw new RuntimeException("Account {$userId} does not exist.");
        $challenge = $this->twoFactor->challengeFor($user);

        if ($challenge !== null) {
            return new TwoFactorChallengeRequired(
                $challenge->token, $challenge->expiresAt, $challenge->enrolment?->secret, $challenge->enrolment?->otpauthUrl, $challenge->enrolment?->qrCode,
            );
        }

        $now = $this->clock->now();
        $expiresAt = $now->modify($this->lifetime->forUser($userId));

        $first = $user->recordSignIn($now);
        $user = $this->users->save($user);
        $token = $this->tokens->issueForUser($userId, $deviceName, $expiresAt);

        $this->events->dispatch([new SignedIn(AccountScope::User, $userId, $ipAddress, $userAgent, $now, $first)]);

        return new AccountSession(
            $token, $expiresAt, $userId, $user->type->value, $user->name(), $user->email(),
            $user->isEmailVerified(), $user->status()->value, $user->locale(),
        );
    }
}
