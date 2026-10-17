<?php

namespace Src\Recruitment\Domain;

use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/**
 * The fixed reasons for withdrawing an application. The values match the check constraints of `withdrawal_reason` on
 * `recruitment_application_stage_history` and `recruitment_applications`.
 */
enum WithdrawalReason: string
{
    case AcceptedOtherOffer = 'accepted_other_offer';
    case Salary = 'salary';
    case Location = 'location';
    case RoleMismatch = 'role_mismatch';
    case ProcessTooLong = 'process_too_long';
    case Personal = 'personal';
    case Unreachable = 'unreachable';
    case Other = 'other';

    /** When the team marks a withdrawal, `other` needs a text note; the others may carry one. */
    public function requiresNoteFromTeam(): bool
    {
        return $this === self::Other;
    }

    /** `unreachable` (the candidate stopped answering) can only be recorded by the team. */
    public function isTeamOnly(): bool
    {
        return $this === self::Unreachable;
    }

    /** The text of the reason in the recipient's language (`id` or `en`), for emails. */
    public function label(string $locale): string
    {
        $id = $locale === 'id';

        return match ($this) {
            self::AcceptedOtherOffer => $id ? 'Menerima tawaran lain' : 'Accepted another offer',
            self::Salary => $id ? 'Gaji' : 'Salary',
            self::Location => $id ? 'Lokasi' : 'Location',
            self::RoleMismatch => $id ? 'Pekerjaan tidak sesuai harapan' : 'The role is not what was expected',
            self::ProcessTooLong => $id ? 'Proses terlalu lama' : 'The process took too long',
            self::Personal => $id ? 'Alasan pribadi' : 'Personal reasons',
            self::Unreachable => $id ? 'Tidak dapat dihubungi' : 'Could not be reached',
            self::Other => $id ? 'Alasan lain' : 'Other reason',
        };
    }

    /**
     * The reason a request names, or null when it names none. The applicant may pick any reason except the team-only one; the team must pick one.
     *
     * @param  string  $code  the error code of the use case (`invalid-withdrawal`, `invalid-answer`)
     *
     * @throws BusinessRuleViolation `$code` with `errors.reason`
     */
    public static function fromRequest(?string $value, bool $byTeam, string $code): ?self
    {
        $value = $value === null ? null : trim($value);
        $reason = $value === null || $value === '' ? null : self::tryFrom($value);

        $message = match (true) {
            $value !== null && $value !== '' && ($reason === null || (! $byTeam && $reason->isTeamOnly())) => 'The reason is not valid.',
            $reason === null && $byTeam => 'The reason is required.',
            default => null,
        };

        if ($message !== null) {
            throw new BusinessRuleViolation($message, $code, extensions: ['errors' => ['reason' => [$message]]]);
        }

        return $reason;
    }
}
