<?php

namespace Src\Recruitment\Application;

use DateTimeZone;
use Src\Recruitment\Domain\Interview;
use Src\Recruitment\Domain\InterviewFormat;
use Src\Shared\Application\Applicant\ApplicantContact;

/** The variables of the interview email templates, from the interview and the job seeker who gets it. The email and its preview build them the same way. */
final class InterviewEmailVariables
{
    private const string ZONE = 'Asia/Jakarta';

    /** @return array<string, string> */
    public static function of(Interview $interview, ApplicantContact $contact, string $jobTitle, string $companyName, int $companyId): array
    {
        return [
            'company_id' => (string) $companyId,
            'job_title' => $jobTitle,
            'company_name' => $companyName,
            'note' => $interview->note === null ? '' : "\n\n".($contact->locale === 'id' ? 'Catatan' : 'Note').': '.$interview->note,
            'starts_at' => $interview->startsAt->setTimezone(new DateTimeZone(self::ZONE))->format('Y-m-d H:i').' WIB',
            'format' => match ($interview->format) {
                InterviewFormat::Online => 'Online',
                InterviewFormat::Onsite => 'Onsite',
                InterviewFormat::Video => 'Video in the platform',
                InterviewFormat::Voice => 'Phone call',
            },
            'place' => match ($interview->format) {
                InterviewFormat::Online => (string) $interview->meetingUrl,
                InterviewFormat::Onsite => (string) $interview->location,
                InterviewFormat::Video => 'In the platform: open your application to join',
                InterviewFormat::Voice => 'We will call you on the number you confirmed',
            },
            'recording' => $interview->recorded ? 'Yes, only if you agree' : 'No',
        ];
    }
}
