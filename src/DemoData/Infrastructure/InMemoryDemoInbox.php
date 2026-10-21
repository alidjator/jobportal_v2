<?php

namespace Src\DemoData\Infrastructure;

use DateTimeImmutable;
use LogicException;
use Src\Company\Application\Invitations\InvitationSender;
use Src\Company\Domain\CompanyRole;
use Src\DemoData\Application\DemoInbox;
use Src\Identity\Application\Auth\VerificationCodeSender;

/**
 * Stands in for the invitation and verification-code emails during a demo run: it keeps the latest link and code per email in memory instead of sending
 * them. With {@see PlainTokenInvitationLink} the link is the plain token itself.
 */
final class InMemoryDemoInbox implements DemoInbox, InvitationSender, VerificationCodeSender
{
    /** @var array<string, string> */
    private array $links = [];

    /** @var array<string, string> */
    private array $codes = [];

    public function send(string $email, string $language, string $companyName, CompanyRole $role, string $link, DateTimeImmutable $expiresAt): void
    {
        $this->links[mb_strtolower($email)] = $link;
    }

    public function sendEmailVerification(string $email, string $name, string $locale, string $code, DateTimeImmutable $expiresAt): void
    {
        $this->codes[mb_strtolower($email)] = $code;
    }

    public function sendPasswordReset(string $email, string $name, string $locale, string $code, DateTimeImmutable $expiresAt): void {}

    public function invitationToken(string $email): string
    {
        return $this->links[mb_strtolower($email)] ?? throw new LogicException("No invitation was sent to {$email}.");
    }

    public function verificationCode(string $email): string
    {
        return $this->codes[mb_strtolower($email)] ?? throw new LogicException("No verification code was sent to {$email}.");
    }
}
