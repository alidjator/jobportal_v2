<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\Events\ReviewCompleted;
use Src\Recruitment\Domain\Events\ReviewRequested;
use Src\Recruitment\Domain\ReviewRequest;
use Src\Recruitment\Domain\ReviewRequestRepository;
use Src\Shared\Application\Accounts\CompanyMemberAccounts;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Notifications\InAppNotifier;
use Src\Shared\Application\Notifications\NotificationChannel;
use Src\Shared\Application\Notifications\NotificationPreferences;

/**
 * Tells the people of a shortlist review (`docs/design/phase-8-hiring-managers.md` section 3): the reviewers when the request is made (in-app, always, and an email by the
 * preference `team_reviews`) and the requester when every answer is in (in-app). The reminder and the overdue notice are sent by {@see SendReviewNotices}, which uses
 * {@see self::emailReviewers()} for the reminder email. In-app and email are separate steps, so a retry of one never repeats the other.
 */
final readonly class NotifyOfReviews
{
    public function __construct(
        private ReviewRequestRepository $requests,
        private JobDirectory $jobs,
        private CompanyDirectory $companies,
        private CompanyMemberAccounts $accounts,
        private InAppNotifier $notifier,
        private NotificationPreferences $preferences,
        private TeamEmailSender $emails,
        private ReviewPageLink $page,
    ) {}

    public function requestedInApp(ReviewRequested $event): void
    {
        $request = $this->requests->find($event->requestId);

        if ($request === null) {
            return;
        }

        $reviewers = $this->requests->reviewerIds([$event->requestId])[$event->requestId] ?? [];
        $count = count($this->requests->applicationIds([$event->requestId])[$event->requestId] ?? []);

        $this->notifier->send($reviewers, ReviewNotificationType::REQUESTED, [...$this->data($request), 'count' => $count]);
    }

    public function requestedEmail(ReviewRequested $event): void
    {
        $request = $this->requests->find($event->requestId);

        if ($request === null) {
            return;
        }

        $reviewers = $this->requests->reviewerIds([$event->requestId])[$event->requestId] ?? [];
        $count = count($this->requests->applicationIds([$event->requestId])[$event->requestId] ?? []);

        $this->emailReviewers($request, array_fill_keys($reviewers, $count), RecruitmentEmailTemplates::TEAM_REVIEW_REQUESTED);
    }

    public function completed(ReviewCompleted $event): void
    {
        $request = $this->requests->find($event->requestId);

        if ($request !== null) {
            $this->notifier->send([$request->requestedBy], ReviewNotificationType::COMPLETED, $this->data($request));
        }
    }

    /**
     * Emails the reviewers who switched `team_reviews` on, each with the number of applications that applies to them.
     *
     * @param  array<int, int>  $counts  the number of applications by reviewer id
     */
    public function emailReviewers(ReviewRequest $request, array $counts, string $template): void
    {
        $wanted = $this->preferences->enabledAmong(array_keys($counts), ReviewNotificationType::PREFERENCE, NotificationChannel::Email);

        if ($wanted === []) {
            return;
        }

        $company = $this->companies->summaries([$request->companyId])[$request->companyId] ?? null;
        $jobTitle = $this->jobs->titles([$request->jobId])[$request->jobId] ?? '';
        $link = $this->page->to((int) $request->id);
        $names = $this->accounts->summaries([...$wanted, $request->requestedBy]);
        $requester = isset($names[$request->requestedBy]) ? $names[$request->requestedBy]->name : '';

        foreach ($wanted as $userId) {
            $account = $names[$userId] ?? null;

            if ($account === null || $account->status !== 'active') {
                continue;
            }

            $id = $account->locale === 'id';

            $this->emails->send($account->email, $account->locale, $template, [
                'company_id' => (string) $request->companyId,
                'name' => $account->name,
                'requester' => $requester,
                'job_title' => $jobTitle,
                'company_name' => $company === null ? '' : $company->name,
                'count' => (string) ($counts[$userId] ?? 0),
                'due_on' => $request->dueOn->format('Y-m-d'),
                'message' => $request->message === null ? '' : "\n\n".($id ? 'Pesan' : 'Message').': '.$request->message,
                'link' => $link === null ? '' : "\n\n".($id ? 'Buka halaman review' : 'Open the review page').': '.$link,
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function data(ReviewRequest $request): array
    {
        return [
            'request_id' => $request->id,
            'job_id' => $request->jobId,
            'job_title' => $this->jobs->titles([$request->jobId])[$request->jobId] ?? '',
            'due_on' => $request->dueOn->format('Y-m-d'),
        ];
    }
}
