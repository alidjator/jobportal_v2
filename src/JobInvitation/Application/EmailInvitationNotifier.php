<?php

namespace Src\JobInvitation\Application;

use Src\JobInvitation\Domain\EmailInvitation;
use Src\JobInvitation\Domain\EmailInvitationChannel;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\RequestContext;
use Src\Shared\Application\Settings\Settings;

/** Writes to the person: the email of an invitation by email, with the link that carries the token. The language is the company's request language (the person has no account to read one from). */
final readonly class EmailInvitationNotifier
{
    public function __construct(
        private EmailInvitationEmailSender $emails,
        private CompanyDirectory $companies,
        private JobDirectory $jobs,
        private Settings $settings,
    ) {}

    public function send(EmailInvitation $invitation, InvitationToken $token, string $language = RequestContext::DEFAULT_LANGUAGE): void
    {
        $link = str_replace('{token}', $token->plain, $this->settings->text(InvitationSettings::EMAIL_CLAIM_URL));

        $this->emails->send((string) $invitation->email, (string) $invitation->name, $language, [
            'company_name' => $this->companies->summaries([$invitation->companyId])[$invitation->companyId]->name ?? '',
            'job_title' => $this->jobs->titles([$invitation->jobId])[$invitation->jobId] ?? '',
            'message' => $invitation->note ?? '',
            'channel' => $this->channelText($invitation->channel, $language),
            'claim_link' => $link,
            'decline_link' => $link.(str_contains($link, '?') ? '&' : '?').'tolak=1',
        ]);
    }

    private function channelText(EmailInvitationChannel $channel, string $language): string
    {
        $id = $language === 'id';

        return match ($channel) {
            EmailInvitationChannel::EmailCv => 'email',
            EmailInvitationChannel::WalkIn => $id ? 'wawancara langsung (walk-in)' : 'a walk-in interview',
            EmailInvitationChannel::Referral => $id ? 'rekomendasi' : 'a referral',
            EmailInvitationChannel::JobFair => $id ? 'bursa kerja' : 'a job fair',
            EmailInvitationChannel::Other => $id ? 'cara lain' : 'another way',
        };
    }
}
