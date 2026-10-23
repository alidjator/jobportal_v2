<?php

namespace Src\Shared\Application\Company;

/** The texts a company set for the messages the platform sends in its name, read by the context that sends them. Provided by Company. */
interface CompanyMessageTexts
{
    /** The company's own message for the email that confirms a received application, or null when it set none. */
    public function applied(int $companyId): ?string;

    /** The company's own note for a candidate who accepted an offer (the hire note), or null when it set none. */
    public function hired(int $companyId): ?string;

    /** The company's own default note for the candidate of a test, used when a default test is sent with a move, or null when it set none. */
    public function assessment(int $companyId): ?string;
}
