<?php

namespace Src\Recruitment\Application;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Src\Recruitment\Domain\Application;
use Src\Recruitment\Domain\ApplicationRepository;
use Src\Recruitment\Domain\ReviewRequest;
use Src\Recruitment\Domain\ReviewRequestRepository;
use Src\Recruitment\Domain\StageType;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Notifications\InAppNotifier;
use Src\Shared\Application\TransactionManager;

/**
 * The time-driven notices of shortlist reviews (`docs/design/phase-8-hiring-managers.md` section 3), run by the hourly recruitment task. The reminder goes to the reviewers who still
 * have active applications to answer, in-app and by email (`team_reviews`), once, from 08:00 Jakarta time of the day before the due day; a request made after that moment gets none. The
 * overdue notice goes in-app to the requester once, after the due day has passed. An open request with nothing left to answer (every application became final) notifies nobody.
 */
final readonly class SendReviewNotices
{
    private const string ZONE = 'Asia/Jakarta';

    private const string REMINDER_AT = '08:00';

    public function __construct(
        private ReviewRequestRepository $requests,
        private ApplicationRepository $applications,
        private ReviewRequestViews $views,
        private JobDirectory $jobs,
        private InAppNotifier $notifier,
        private NotifyOfReviews $notify,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /** @return array{reminded: int, overdue: int} */
    public function execute(): array
    {
        $now = $this->clock->now();
        $today = $this->views->today();

        return ['reminded' => $this->remind($now, $today), 'overdue' => $this->overdue($now, $today)];
    }

    private function remind(DateTimeImmutable $now, DateTimeImmutable $today): int
    {
        $count = 0;

        foreach ($this->requests->dueForReminder($today, $today->add(new DateInterval('P1D'))) as $request) {
            $at = $this->reminderAt($request);

            if ($now < $at || ! $this->requests->claimReminder((int) $request->id, $now)) {
                continue;
            }

            $pending = $request->createdAt >= $at ? [] : $this->pendingByReviewer($request);

            if ($pending === []) {
                continue;
            }

            $this->notifier->send(array_keys($pending), ReviewNotificationType::REMINDER, $this->data($request));
            $this->notify->emailReviewers($request, $pending, RecruitmentEmailTemplates::TEAM_REVIEW_REMINDER);
            $count++;
        }

        return $count;
    }

    private function overdue(DateTimeImmutable $now, DateTimeImmutable $today): int
    {
        $count = 0;

        foreach ($this->requests->dueForOverdueNotice($today) as $request) {
            $sent = $this->transactions->run(function () use ($request, $now): bool {
                if (! $this->requests->claimOverdueNotice((int) $request->id, $now)) {
                    return false;
                }

                $pending = $this->pendingByReviewer($request);

                if ($pending === []) {
                    return false;
                }

                $this->notifier->send([$request->requestedBy], ReviewNotificationType::OVERDUE, [...$this->data($request), 'pending' => array_sum($pending)]);

                return true;
            });

            $count += $sent ? 1 : 0;
        }

        return $count;
    }

    private function reminderAt(ReviewRequest $request): DateTimeImmutable
    {
        return new DateTimeImmutable($request->dueOn->sub(new DateInterval('P1D'))->format('Y-m-d').' '.self::REMINDER_AT, new DateTimeZone(self::ZONE));
    }

    /**
     * The number of active applications each reviewer has not answered, for the reviewers with at least one.
     *
     * @return array<int, int>
     */
    private function pendingByReviewer(ReviewRequest $request): array
    {
        $id = (int) $request->id;
        $reviewers = $this->requests->reviewerIds([$id])[$id] ?? [];
        $given = [];

        foreach ($this->requests->answers([$id])[$id] ?? [] as $answer) {
            $given[$answer->applicationId][] = $answer->reviewerId;
        }

        $pending = [];

        foreach ($this->requests->applicationIds([$id])[$id] ?? [] as $applicationId) {
            $application = $this->applications->find($applicationId);

            if (! $application instanceof Application || $application->stageType->isTerminal() || $application->stageType === StageType::Hired) {
                continue;
            }

            foreach (array_diff($reviewers, $given[$applicationId] ?? []) as $reviewerId) {
                $pending[$reviewerId] = ($pending[$reviewerId] ?? 0) + 1;
            }
        }

        return $pending;
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
