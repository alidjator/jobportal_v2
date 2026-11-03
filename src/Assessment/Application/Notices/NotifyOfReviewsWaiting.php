<?php

namespace Src\Assessment\Application\Notices;

use Src\Assessment\Application\AssessmentEmailTemplates;
use Src\Assessment\Application\Delivery\AssessmentNotificationType;
use Src\Assessment\Domain\ProviderNoticeRepository;
use Src\Assessment\Domain\ReviewsWaiting;
use Src\Shared\Application\Accounts\PsychologistAccounts;
use Src\Shared\Application\Notifications\InAppNotifier;
use Src\Shared\Application\Notifications\NotificationChannel;
use Src\Shared\Application\Notifications\NotificationPreferences;

/**
 * Tells each active psychologist how many reviews wait at their provider (reports nobody holds and automatic reports nobody reviewed): one in-app notice and one email when that count went up since
 * the last notice, nothing when it did not change. A count that fell is only remembered, so a later rise is told again. The email follows the preference `provider_reviews` (on by default).
 * It runs every hour (`phase-8-provider-tools.md` section 5).
 */
final readonly class NotifyOfReviewsWaiting
{
    public function __construct(
        private ProviderNoticeRepository $notices,
        private InAppNotifier $notifier,
        private NotificationPreferences $preferences,
        private PsychologistAccounts $accounts,
        private ProviderNoticeSender $emails,
    ) {}

    /** @return int the number of psychologists told */
    public function execute(): int
    {
        $rows = $this->notices->reviewsWaiting();
        $told = array_values(array_filter($rows, static fn (ReviewsWaiting $row): bool => $row->count > $row->notified));

        foreach ($rows as $row) {
            if ($row->count < $row->notified) {
                $this->notices->markReviewsNotified($row->psychologistId, $row->count);
            }
        }

        if ($told === []) {
            return 0;
        }

        $userIds = array_map(static fn (ReviewsWaiting $row): int => $row->userId, $told);
        $wanted = $this->preferences->enabledAmong($userIds, AssessmentNotificationType::REVIEWS_PREFERENCE, NotificationChannel::Email);
        $accounts = $this->accounts->summaries($userIds);

        foreach ($told as $row) {
            $this->notifier->send([$row->userId], AssessmentNotificationType::PROVIDER_REVIEWS_WAITING, ['count' => $row->count]);

            $account = $accounts[$row->userId] ?? null;

            if ($account !== null && $account->status === 'active' && in_array($row->userId, $wanted, true)) {
                $this->emails->send($account->email, $account->locale, AssessmentEmailTemplates::PROVIDER_REVIEWS_WAITING, ['name' => $account->name, 'count' => (string) $row->count]);
            }

            $this->notices->markReviewsNotified($row->psychologistId, $row->count);
        }

        return count($told);
    }
}
