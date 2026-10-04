<?php

namespace Src\Identity\Interface\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Src\Identity\Application\Admin\AdminRoles;
use Src\Identity\Application\Admin\CreateAdmin;
use Src\Shared\Domain\Exceptions\DomainException;

/** The operator's way to create the first administrator. The password is read from a hidden prompt, never from an argument or option. */
final class CreateAdminCommand extends Command
{
    protected $signature = 'identity:create-admin {--name= : Full name} {--email= : Email address} {--role=super_admin : Role to assign}';

    protected $description = 'Create an administrator (run identity:sync-permissions first so the role exists)';

    public function handle(CreateAdmin $createAdmin): int
    {
        $name = (string) ($this->option('name') ?: $this->ask('Name'));
        $email = (string) ($this->option('email') ?: $this->ask('Email address'));
        $password = (string) $this->secret('Password (minimum 8 characters)');

        if ($password !== (string) $this->secret('Repeat the password')) {
            $this->error('The passwords do not match.');

            return self::FAILURE;
        }

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password],
            ['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255'], 'password' => ['required', 'string', 'min:8', 'max:255']],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        try {
            $admin = $createAdmin->execute($name, $email, $password, (string) $this->option('role') ?: AdminRoles::SUPER_ADMIN);
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Administrator {$admin->email()} created (id {$admin->id}).");

        return self::SUCCESS;
    }
}
