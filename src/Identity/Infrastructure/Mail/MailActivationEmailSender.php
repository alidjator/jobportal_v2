<?php

namespace Src\Identity\Infrastructure\Mail;

use Illuminate\Contracts\Mail\Mailer;
use Src\Identity\Application\Activation\ActivationEmailSender;
use Src\Identity\Application\Activation\ActivationLinks;
use Src\Identity\Application\IdentityEmailTemplates;
use Src\Identity\Domain\ActivationKind;
use Src\Identity\Domain\ActivationVariant;
use Src\Identity\Domain\User;
use Src\Identity\Domain\UserType;
use Src\Shared\Application\Settings\Settings;

/** Queues the activation email in the language of the account. The email is essential (it passes the suppression listener); the command already skips suppressed addresses. */
final readonly class MailActivationEmailSender implements ActivationEmailSender
{
    public function __construct(
        private Mailer $mailer,
        private ActivationLinks $links,
        private Settings $settings,
    ) {}

    public function send(User $user, ActivationKind $kind, ActivationVariant $variant, string $companyName): void
    {
        $audience = $variant === ActivationVariant::Company ? UserType::CompanyMember : UserType::JobSeeker;
        $support = $this->settings->text('platform.support_email');
        $whatsapp = $this->settings->text('platform.support_whatsapp');

        $variables = [
            'name' => $user->name(),
            'company_name' => $companyName,
            'sign_in_url' => (string) $this->links->signInUrl(),
            'activation_url' => (string) $this->links->activationUrl($audience),
            'reminder_days' => (string) $this->settings->integer('identity.activation_reminder_days'),
            'support_email' => $support,
            'support_contact' => $whatsapp === '' ? $support : "{$support} / WhatsApp {$whatsapp}",
        ];

        $this->mailer->to($user->email())->locale($user->locale())->queue(new AccountNoticeMail($this->templateKey($kind, $variant), $user->locale(), $variables));
    }

    private function templateKey(ActivationKind $kind, ActivationVariant $variant): string
    {
        if ($kind === ActivationKind::Reminder) {
            return IdentityEmailTemplates::ACTIVATION_REMINDER;
        }

        return match ($variant) {
            ActivationVariant::Company => IdentityEmailTemplates::ACTIVATION_COMPANY,
            ActivationVariant::HasPassword => IdentityEmailTemplates::ACTIVATION_JOB_SEEKER,
            ActivationVariant::NoPassword => IdentityEmailTemplates::ACTIVATION_JOB_SEEKER_NO_PASSWORD,
        };
    }
}
