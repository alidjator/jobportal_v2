<?php

namespace Src\Assessment\Application\Messages;

use Src\Assessment\Application\AssessmentEmailTemplates;
use Src\Assessment\Application\Delivery\AssessmentNotificationType;
use Src\Assessment\Application\Notices\ProviderNoticeSender;
use Src\Assessment\Domain\Invitation;
use Src\Assessment\Domain\Psychologist;
use Src\Assessment\Domain\PsychologistRepository;
use Src\Assessment\Domain\Report;
use Src\Assessment\Domain\ReportMessageRepository;
use Src\Shared\Application\Accounts\PsychologistAccounts;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Notifications\InAppNotifier;
use Src\Shared\Application\Notifications\NotificationChannel;
use Src\Shared\Application\Notifications\NotificationPreferences;

/**
 * Tells the other side that a message about a report arrived (`docs/design/phase-8-provider-institutions.md` section 6). A company question gives an in-app notice to the signer and to the leads of the institution and
 * an email to the signer (to the leads when the signer is no longer active), which follows the preference `provider_reviews`; an answer gives an in-app notice to the company members who asked.
 */
final readonly class ReportMessageNotices
{
    public function __construct(
        private PsychologistRepository $psychologists,
        private PsychologistAccounts $accounts,
        private CompanyDirectory $companies,
        private ReportMessageRepository $messages,
        private InAppNotifier $notifier,
        private NotificationPreferences $preferences,
        private ProviderNoticeSender $emails,
    ) {}

    public function questionAsked(Report $report, Invitation $invitation, string $testTitle): void
    {
        $team = $this->psychologists->ofProvider($report->providerId);
        $signer = $report->signedByPsychologistId === null ? null : $this->psychologists->findById($report->signedByPsychologistId);
        $leads = array_values(array_filter($team, static fn (Psychologist $member): bool => $member->isActive() && $member->isLead()));

        $inApp = array_values(array_unique(array_map(static fn (Psychologist $member): int => $member->userId, [...($signer?->isActive() ? [$signer] : []), ...$leads])));
        $company = $this->companies->summaries([$invitation->companyId])[$invitation->companyId]->name ?? '';
        $number = (string) $report->number;

        $this->notifier->send($inApp, AssessmentNotificationType::PROVIDER_REPORT_QUESTION, [
            'report_id' => $report->id, 'company_name' => $company, 'test_title' => $testTitle, 'report_number' => $number,
        ]);

        // The signer is the one to answer; the leads get the email only when the signer cannot.
        $recipients = $signer !== null && $signer->isActive() ? [$signer] : $leads;
        $userIds = array_map(static fn (Psychologist $member): int => $member->userId, $recipients);
        $wanted = $this->preferences->enabledAmong($userIds, AssessmentNotificationType::REVIEWS_PREFERENCE, NotificationChannel::Email);
        $accounts = $this->accounts->summaries($userIds);

        foreach ($userIds as $userId) {
            $account = $accounts[$userId] ?? null;

            if ($account !== null && $account->status === 'active' && in_array($userId, $wanted, true)) {
                $this->emails->send($account->email, $account->locale, AssessmentEmailTemplates::REPORT_QUESTION, [
                    'name' => $account->name, 'company_name' => $company, 'test_title' => $testTitle, 'report_number' => $number,
                ]);
            }
        }
    }

    public function answered(Report $report, Invitation $invitation, string $testTitle): void
    {
        $this->notifier->send($this->messages->askerUserIds((int) $report->id), AssessmentNotificationType::REPORT_ANSWERED, [
            'invitation_id' => $invitation->id, 'test_title' => $testTitle, 'report_number' => (string) $report->number,
        ]);
    }
}
