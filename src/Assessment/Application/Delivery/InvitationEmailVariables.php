<?php

namespace Src\Assessment\Application\Delivery;

use DateTimeImmutable;
use DateTimeZone;
use Src\Assessment\Domain\InvitationMode;
use Src\Shared\Application\Applicant\ApplicantContact;

/** The variables of the test email templates, from the invitation and the candidate who gets it. The email and its preview build them the same way. */
final class InvitationEmailVariables
{
    private const string ZONE = 'Asia/Jakarta';

    /** @return array<string, string> */
    public static function of(string $testTitle, DateTimeImmutable $dueAt, InvitationMode $mode, ?string $location, ?string $note, ApplicantContact $contact, string $jobTitle, string $companyName, int $companyId): array
    {
        return [
            'company_id' => (string) $companyId,
            'job_title' => $jobTitle,
            'company_name' => $companyName,
            'test_title' => $testTitle,
            'due_at' => $dueAt->setTimezone(new DateTimeZone(self::ZONE))->format('Y-m-d H:i').' WIB',
            'location' => $mode === InvitationMode::Onsite ? ($contact->locale === 'id' ? 'Lokasi: ' : 'Location: ').$location : '',
            'note' => $note === null ? '' : "\n\n".($contact->locale === 'id' ? 'Catatan' : 'Note').': '.$note,
        ];
    }
}
