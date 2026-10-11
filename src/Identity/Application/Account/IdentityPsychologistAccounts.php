<?php

namespace Src\Identity\Application\Account;

use RuntimeException;
use Src\Identity\Application\Auth\AccessTokenIssuer;
use Src\Identity\Application\Auth\PasswordHasher;
use Src\Identity\Application\TwoFactor\UserTwoFactors;
use Src\Identity\Domain\Events\UserRegistered;
use Src\Identity\Domain\User;
use Src\Identity\Domain\UserRepository;
use Src\Shared\Application\Accounts\MemberAccountSummary;
use Src\Shared\Application\Accounts\PsychologistAccounts;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;

/** Identity's side of the Assessment context's needs: psychologist accounts are created and closed here, nowhere else. */
final readonly class IdentityPsychologistAccounts implements PsychologistAccounts
{
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $hasher,
        private AccessTokenIssuer $tokens,
        private UserTwoFactors $factors,
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
        $user = $this->users->save(User::createPsychologist(
            $name, mb_strtolower(trim($email)), $this->hasher->make($password), $locale, $now,
        ));

        $this->events->dispatch([new UserRegistered((int) $user->id, $user->type->value, $now)]);

        return (int) $user->id;
    }

    public function deactivate(int $userId): void
    {
        $user = $this->find($userId);

        $user->deactivate();
        $this->users->save($user);
        $this->tokens->revokeAllForUser($userId);
    }

    public function reactivate(int $userId): void
    {
        $user = $this->find($userId);

        $user->reactivate();
        $this->users->save($user);
    }

    public function resetTwoFactor(int $userId): void
    {
        $this->find($userId);

        $this->factors->reset($userId);
        $this->tokens->revokeAllForUser($userId);
    }

    public function summaries(array $userIds): array
    {
        $summaries = [];

        foreach ($this->users->findByIds($userIds) as $id => $user) {
            $summaries[$id] = new MemberAccountSummary($id, $user->name(), $user->email(), $user->status()->value, $user->lastLoginAt());
        }

        return $summaries;
    }

    private function find(int $userId): User
    {
        $user = $this->users->findById($userId);

        return $user !== null && $user->isPsychologist() ? $user : throw new RuntimeException("Psychologist account {$userId} does not exist.");
    }
}
