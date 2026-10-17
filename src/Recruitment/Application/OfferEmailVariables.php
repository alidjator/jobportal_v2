<?php

namespace Src\Recruitment\Application;

use DateTimeZone;
use Src\Recruitment\Domain\Offer;
use Src\Shared\Application\Applicant\ApplicantContact;

/** The variables of the offer email templates, from the offer and the job seeker who gets it. The email and its preview build them the same way. */
final class OfferEmailVariables
{
    private const string ZONE = 'Asia/Jakarta';

    /** @return array<string, string> */
    public static function of(Offer $offer, ApplicantContact $contact, string $jobTitle, string $companyName, int $companyId): array
    {
        return [
            'company_id' => (string) $companyId,
            'job_title' => $jobTitle,
            'company_name' => $companyName,
            'position' => $offer->position,
            'salary' => 'Rp '.number_format($offer->salary, 0, ',', '.'),
            'start_date' => $offer->startDate->format('Y-m-d'),
            'expires_at' => $offer->expiresAt->setTimezone(new DateTimeZone(self::ZONE))->format('Y-m-d H:i').' WIB',
            'note' => $offer->note === null ? '' : "\n\n".($contact->locale === 'id' ? 'Catatan' : 'Note').': '.$offer->note,
        ];
    }
}
