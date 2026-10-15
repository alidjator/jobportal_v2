<?php

namespace Src\Identity\Infrastructure\Privacy;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Src\Shared\Application\Privacy\ExportedFile;
use Src\Shared\Application\Privacy\PersonalDataExporter;
use Src\Shared\Application\Privacy\PersonalDataSection;

/** The Identity part of the personal data export: the account itself, the terms accepted (with the time and the address they were accepted from), the linked sign-in providers and the profile picture. Never the password hash. */
final readonly class AccountDataExporter implements PersonalDataExporter
{
    public function __construct(private ConnectionInterface $connection) {}

    public function key(): string
    {
        return 'account';
    }

    public function export(int $userId): PersonalDataSection
    {
        $user = $this->connection->table('identity_users')->where('id', $userId)->first();

        if ($user === null) {
            return new PersonalDataSection([]);
        }

        $files = [];
        $avatar = null;

        if (is_string($user->avatar_path) && $user->avatar_path !== '') {
            $avatar = 'avatar.'.(pathinfo($user->avatar_path, PATHINFO_EXTENSION) ?: 'jpg');
            $files[] = new ExportedFile($user->avatar_path, $avatar);
        }

        return new PersonalDataSection([
            'email' => $user->email,
            'name' => $user->name,
            'type' => $user->type,
            'status' => $user->status,
            'language' => $user->locale,
            'email_verified_at' => self::time($user->email_verified_at),
            'created_at' => self::time($user->created_at),
            'first_sign_in_at' => self::time($user->first_login_at),
            'last_sign_in_at' => self::time($user->last_login_at),
            'deletion_requested_at' => self::time($user->deletion_requested_at),
            'registration_campaign_code' => $user->registration_campaign_code,
            'has_password' => $user->password !== null,
            'avatar' => $avatar === null ? null : 'files/account/'.$avatar,
            'terms_accepted' => $this->connection->table('identity_terms_acceptances')->where('user_id', $userId)->orderBy('accepted_at')->orderBy('id')->get()
                ->map(static fn (object $row): array => [
                    'document' => $row->document_type,
                    'version' => (int) $row->document_version,
                    'language' => $row->locale,
                    'accepted_at' => self::time($row->accepted_at),
                    'ip_address' => $row->ip_address,
                ])->all(),
            'sign_in_providers' => $this->connection->table('identity_social_accounts')->where('user_id', $userId)->orderBy('id')->get()
                ->map(static fn (object $row): array => ['provider' => $row->provider, 'email' => $row->email, 'linked_at' => self::time($row->created_at)])->all(),
        ], $files);
    }

    private static function time(?string $value): ?string
    {
        return $value === null ? null : (new DateTimeImmutable($value, new DateTimeZone('UTC')))->format(DateTimeInterface::ATOM);
    }
}
