<?php

namespace Src\Matching\Infrastructure\Mail;

use Illuminate\Contracts\Mail\Mailer;
use Src\Matching\Application\RecommendationEmailSender;
use Src\Shared\Application\Applicant\ApplicantContact;

final readonly class MailRecommendationSender implements RecommendationEmailSender
{
    public function __construct(private Mailer $mailer) {}

    public function send(ApplicantContact $to, array $variables): void
    {
        $this->mailer->to($to->email)->locale($to->locale)->queue(new RecommendationMail(['name' => $to->name, ...$variables], $to->locale));
    }
}
