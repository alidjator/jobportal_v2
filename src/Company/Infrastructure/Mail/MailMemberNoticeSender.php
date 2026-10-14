<?php

namespace Src\Company\Infrastructure\Mail;

use Illuminate\Contracts\Mail\Mailer;
use Src\Company\Application\CompanyEmailTemplates;
use Src\Company\Application\Team\MemberNoticeSender;

final readonly class MailMemberNoticeSender implements MemberNoticeSender
{
    public function __construct(private Mailer $mailer) {}

    public function suspended(string $email, string $language, string $companyName, ?string $message): void
    {
        $this->queue($email, $language, CompanyEmailTemplates::MEMBER_SUSPENDED, ['company' => $companyName, 'message' => $message ?? '']);
    }

    public function reactivated(string $email, string $language, string $companyName): void
    {
        $this->queue($email, $language, CompanyEmailTemplates::MEMBER_REACTIVATED, ['company' => $companyName]);
    }

    public function ownershipGained(string $email, string $language, string $companyName, ?string $previousOwner): void
    {
        $this->queue($email, $language, CompanyEmailTemplates::OWNERSHIP_GAINED, [
            'company' => $companyName,
            'previous_owner' => $previousOwner === null ? '' : ($language === 'id' ? "Sebelumnya: {$previousOwner}." : "Previously: {$previousOwner}."),
        ]);
    }

    public function ownershipHandedOver(string $email, string $language, string $companyName, string $newOwner): void
    {
        $this->queue($email, $language, CompanyEmailTemplates::OWNERSHIP_HANDED_OVER, ['company' => $companyName, 'new_owner' => $newOwner]);
    }

    /** @param  array<string, string>  $variables */
    private function queue(string $email, string $language, string $template, array $variables): void
    {
        $this->mailer->to($email)->locale($language)->queue(new MemberNoticeMail($template, $language, $variables));
    }
}
