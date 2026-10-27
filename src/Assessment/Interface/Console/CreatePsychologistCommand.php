<?php

namespace Src\Assessment\Interface\Console;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Src\Assessment\Application\Admin\CreatePsychologistAccount;
use Src\Assessment\Domain\PsychologistRole;
use Src\Shared\Domain\Exceptions\DomainException;
use Src\Shared\Interface\Http\Rules\NewPassword;

/** The operator's way to create a psychologist without the invitation email. The password is read from a hidden prompt, never from an argument or option. */
final class CreatePsychologistCommand extends Command
{
    protected $signature = 'assessment:create-psychologist
        {--provider= : Id of the provider (institution) the psychologist belongs to}
        {--name= : Full name}
        {--email= : Email address}
        {--role=lead : member or lead}
        {--str=STR-DEV : STR number}
        {--silp=SIPP-DEV : SILP number}
        {--valid-until= : Day both licences are valid through (default: five years from today)}';

    protected $description = 'Create a psychologist account under a provider (the second factor is enrolled at the first sign-in)';

    public function handle(CreatePsychologistAccount $create): int
    {
        $validUntil = (string) ($this->option('valid-until') ?: now()->addYears(5)->format('Y-m-d'));
        $input = [
            'provider' => (string) ($this->option('provider') ?: $this->ask('Provider id')),
            'name' => (string) ($this->option('name') ?: $this->ask('Name')),
            'email' => (string) ($this->option('email') ?: $this->ask('Email address')),
            'role' => (string) $this->option('role'),
            'str' => (string) $this->option('str'),
            'silp' => (string) $this->option('silp'),
            'valid_until' => $validUntil,
            'password' => (string) $this->secret('Password (minimum 8 characters)'),
        ];

        if ($input['password'] !== (string) $this->secret('Repeat the password')) {
            $this->error('The passwords do not match.');

            return self::FAILURE;
        }

        $validator = Validator::make($input, [
            'provider' => ['required', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::enum(PsychologistRole::class)],
            'str' => ['required', 'string', 'min:3', 'max:60'],
            'silp' => ['required', 'string', 'min:3', 'max:60'],
            'valid_until' => ['required', 'date_format:Y-m-d'],
            'password' => NewPassword::rules(),
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $until = new DateTimeImmutable($input['valid_until'], new DateTimeZone('UTC'));

        try {
            $created = $create->execute(
                (int) $input['provider'], $input['name'], $input['email'], $input['password'], PsychologistRole::from($input['role']),
                $input['str'], $until, $input['silp'], $until,
            );
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Psychologist {$input['email']} created (id {$created['psychologistId']}). Sign in at /mitra/masuk and enrol the second factor.");

        return self::SUCCESS;
    }
}
