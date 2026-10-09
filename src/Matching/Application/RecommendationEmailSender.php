<?php

namespace Src\Matching\Application;

use Src\Shared\Application\Applicant\ApplicantContact;

/** Sends the recommendation email; the text comes from the `matching.recommendations` template. Implemented in Infrastructure. */
interface RecommendationEmailSender
{
    /** @param  array<string, string>  $variables  the template variables besides the recipient's name */
    public function send(ApplicantContact $to, array $variables): void;
}
