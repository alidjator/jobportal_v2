<?php

namespace Src\Identity\Interface\Console;

use Illuminate\Console\Command;
use Src\Identity\Application\TwoFactor\ResetUserTwoFactor;
use Src\Shared\Domain\Exceptions\DomainException;

/** The operator's answer to a lost authenticator device of a company member or a psychologist whom no one else can reset (a lone company admin). */
final class ResetUserTwoFactorCommand extends Command
{
    use NamesTheOperator;

    protected $signature = 'identity:reset-user-two-factor {email : Email address of the company member or psychologist}';

    protected $description = 'Remove the second factor of a company member or psychologist (lost device); they enrol again at the next sign-in';

    public function handle(ResetUserTwoFactor $reset): int
    {
        $email = (string) $this->argument('email');

        if (! $this->confirm("Remove the second factor and every session of {$email}?")) {
            return self::FAILURE;
        }

        try {
            $userId = $reset->execute($email, $this->operator());
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Second factor of user {$userId} removed; they must enrol again at the next sign-in.");

        return self::SUCCESS;
    }
}
