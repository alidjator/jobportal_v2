<?php

namespace Src\Identity\Interface\Console;

use Illuminate\Console\Command;
use Src\Identity\Application\TwoFactor\ResetAdminTwoFactor;
use Src\Shared\Domain\Exceptions\DomainException;

/** The operator's answer to a lost authenticator device: the admin is signed out everywhere and enrols a new device at the next sign-in. */
final class ResetAdminTwoFactorCommand extends Command
{
    protected $signature = 'identity:reset-admin-two-factor {email : Email address of the administrator}';

    protected $description = 'Remove the second factor of an administrator (lost device); they enrol again at the next sign-in';

    public function handle(ResetAdminTwoFactor $reset): int
    {
        $email = (string) $this->argument('email');

        if (! $this->confirm("Remove the second factor and every session of {$email}?")) {
            return self::FAILURE;
        }

        try {
            $adminId = $reset->execute($email);
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Second factor of administrator {$adminId} removed; they must enrol again at the next sign-in.");

        return self::SUCCESS;
    }
}
