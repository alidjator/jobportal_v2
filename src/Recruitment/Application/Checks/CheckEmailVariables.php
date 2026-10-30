<?php

namespace Src\Recruitment\Application\Checks;

use DateTimeZone;
use Src\Recruitment\Domain\ApplicationCheck;
use Src\Shared\Application\Applicant\ApplicantContact;

/** The variables of the check email templates, from the check and the job seeker who gets it. The lines that may be empty carry their own label and line breaks, as the offer templates do. */
final class CheckEmailVariables
{
    private const string ZONE = 'Asia/Jakarta';

    /** @return array<string, string> */
    public static function of(ApplicationCheck $check, ApplicantContact $contact, string $checkName, string $jobTitle, string $companyName, int $companyId): array
    {
        $indonesian = $contact->locale === 'id';

        return [
            'company_id' => (string) $companyId,
            'job_title' => $jobTitle,
            'company_name' => $companyName,
            'check_name' => $checkName,
            'schedule' => $check->scheduledAt === null ? '' : "\n\n".($indonesian ? 'Waktu' : 'Time').': '.$check->scheduledAt->setTimezone(new DateTimeZone(self::ZONE))->format('Y-m-d H:i').' WIB',
            'place' => $check->place === null ? '' : "\n".($indonesian ? 'Tempat' : 'Place').': '.$check->place,
            'vendor' => $check->vendor === null ? '' : "\n".($indonesian ? 'Pelaksana' : 'Provided by').': '.$check->vendor,
            'note' => $check->noteToCandidate === null ? '' : "\n\n".($indonesian ? 'Catatan' : 'Note').': '.$check->noteToCandidate,
            'consent' => $check->awaitsConsent()
                ? "\n\n".($indonesian ? 'Pemeriksaan ini memerlukan persetujuan Anda. Buka akun Anda untuk menyetujui atau menolaknya.' : 'This check needs your consent. Open your account to agree or refuse.')
                : '',
        ];
    }
}
