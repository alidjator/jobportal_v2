<?php

namespace Src\Identity\Application\Commands;

use Illuminate\Support\Str;
use Src\Identity\Domain\AccountNotifier;
use Src\Identity\Domain\NewAccount;
use Src\Identity\Domain\UserRepository;

final class Register
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly AccountNotifier $notifier,
    ) {}

    /**
     * `role` selain `candidate` dianggap `company` (sama dengan aplikasi lama).
     *
     * @return array<string,mixed> user yang baru dibuat
     */
    public function __invoke(string $name, string $email, string $password, mixed $role): array
    {
        $username = Str::slug($name);

        if ($username === '' || $this->users->usernameExists($username)) {
            $username = ($username === '' ? 'user' : $username).'_'.Str::random(5);
        }

        $user = $this->users->create(new NewAccount(
            role: $role === 'candidate' ? 'candidate' : 'company',
            name: $name,
            email: $email,
            username: $username,
            password: $password,
        ));

        $this->notifier->userRegistered($user);

        return $user;
    }
}
