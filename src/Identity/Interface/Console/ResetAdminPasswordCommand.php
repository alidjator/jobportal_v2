<?php

namespace Src\Identity\Interface\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Src\Identity\Application\Admin\ResetAdminPassword;
use Src\Shared\Domain\Exceptions\DomainException;
use Src\Shared\Interface\Http\Rules\NewPassword;

/** The operator's answer to a forgotten admin password. The password is read from a hidden prompt, never from an argument or option. */
final class ResetAdminPasswordCommand extends Command
{
    use NamesTheOperator;

    protected $signature = 'identity:reset-admin-password {email : Email address of the administrator}';

    protected $description = 'Set a new password for an administrator and end every session';

    public function handle(ResetAdminPassword $reset): int
    {
        $password = (string) $this->secret('New password (minimum 8 characters)');

        if ($password !== (string) $this->secret('Repeat the password')) {
            $this->error('The passwords do not match.');

            return self::FAILURE;
        }

        $validator = Validator::make(['password' => $password], ['password' => NewPassword::rules()]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        try {
            $adminId = $reset->execute((string) $this->argument('email'), $password, $this->operator());
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Password of administrator {$adminId} changed; every session ended.");

        return self::SUCCESS;
    }
}
