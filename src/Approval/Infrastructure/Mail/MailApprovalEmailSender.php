<?php

namespace Src\Approval\Infrastructure\Mail;

use Illuminate\Contracts\Mail\Mailer;
use Src\Approval\Application\ApprovalEmailSender;

final readonly class MailApprovalEmailSender implements ApprovalEmailSender
{
    public function __construct(private Mailer $mailer) {}

    public function send(string $email, string $language, string $template, array $variables): void
    {
        $this->mailer->to($email)->locale($language)->queue(new ApprovalNoticeMail($template, $language, $variables));
    }
}
