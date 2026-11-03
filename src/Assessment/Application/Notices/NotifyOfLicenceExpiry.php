<?php

namespace Src\Assessment\Application\Notices;

use DateTimeImmutable;
use DateTimeZone;
use Src\Assessment\Application\AssessmentEmailTemplates;
use Src\Assessment\Application\AssessmentPermissions;
use Src\Assessment\Application\Delivery\AssessmentNotificationType;
use Src\Assessment\Domain\ProviderNoticeRepository;
use Src\Assessment\Domain\PsychologistLicences;
use Src\Shared\Application\Accounts\PsychologistAccounts;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Notifications\AdminInAppNotifier;
use Src\Shared\Application\Notifications\InAppNotifier;

/**
 * Tells a psychologist (in the app and by email, which is always sent: the licence decides whether reports can be signed) and the platform admins who manage providers (in the app) that an STR
 * or SILP ends in 60, 30 or 7 days, and on the day it ends. Each threshold is told once per licence and end date, so a renewed licence starts again; a licence found already inside a threshold is
 * told once, for the smallest, and the larger ones count as told. When a licence has ended, the batteries the psychologist answers for stop issuing automatically (their reports wait for a
 * psychologist, as the issue falls back) and the psychologists of the provider are told which battery. It runs every day (`phase-8-provider-tools.md` section 5).
 */
final readonly class NotifyOfLicenceExpiry
{
    /** Days before the end at which the notices go out; 0 is the day it ends. */
    public const array THRESHOLDS = [60, 30, 7, 0];

    private const string STR = 'str';

    private const string SILP = 'silp';

    public function __construct(
        private ProviderNoticeRepository $notices,
        private InAppNotifier $notifier,
        private AdminInAppNotifier $admins,
        private PsychologistAccounts $accounts,
        private ProviderNoticeSender $emails,
        private Clock $clock,
    ) {}

    /** @return int the number of licence notices sent to psychologists */
    public function execute(): int
    {
        $zone = new DateTimeZone('Asia/Jakarta');
        $today = $this->clock->now()->setTimezone($zone)->setTime(0, 0);
        $rows = $this->notices->psychologistsWithLicences();
        $accounts = $this->accounts->summaries(array_map(static fn (PsychologistLicences $row): int => $row->userId, $rows));
        $count = 0;

        foreach ($rows as $row) {
            $notified = $row->notified;
            $ended = false;

            foreach ([self::STR => $row->strValidUntil, self::SILP => $row->silpValidUntil] as $kind => $validUntil) {
                $until = $validUntil->setTimezone($zone)->setTime(0, 0);
                $daysLeft = (int) $today->diff($until)->format('%r%a');
                $due = $this->due($notified[$kind] ?? null, $until, $daysLeft);

                if ($due === null) {
                    continue;
                }

                $notified[$kind] = ['valid_until' => $until->format('Y-m-d'), 'sent' => $due['sent']];
                $ended = $ended || $due['ended'];
                $account = $accounts[$row->userId] ?? null;
                $name = $account === null ? '' : $account->name;
                $data = ['kind' => $kind, 'valid_until' => $until->format('Y-m-d'), 'days_left' => $daysLeft];

                $this->notifier->send([$row->userId], AssessmentNotificationType::LICENCE_EXPIRING, $data);
                $this->admins->sendToHolders(AssessmentPermissions::PROVIDERS_MANAGE, AssessmentNotificationType::PROVIDER_LICENCE_EXPIRING, [
                    ...$data,
                    'provider_id' => $row->providerId,
                    'provider_name' => $row->providerName,
                    'psychologist_name' => $name,
                ]);

                if ($account !== null && $account->status === 'active') {
                    $this->emails->send($account->email, $account->locale, AssessmentEmailTemplates::LICENCE_EXPIRING, [
                        'name' => $account->name,
                        'kind' => strtoupper($kind),
                        'valid_until' => $until->format('Y-m-d'),
                        'days' => (string) max(0, $daysLeft),
                    ]);
                }

                $count++;
            }

            if ($notified !== $row->notified) {
                $this->notices->markLicenceNotified($row->psychologistId, $notified);
            }

            if ($ended) {
                $this->pauseAutoIssue($row);
            }
        }

        return $count;
    }

    /**
     * The thresholds told after this run, or null when there is nothing new to tell: the thresholds the licence is inside now that the earlier runs did not tell, for the end date it has now.
     *
     * @return array{sent: list<int>, ended: bool}|null
     */
    private function due(mixed $state, DateTimeImmutable $until, int $daysLeft): ?array
    {
        $sent = is_array($state) && ($state['valid_until'] ?? null) === $until->format('Y-m-d') && is_array($state['sent'] ?? null) ? array_map('intval', $state['sent']) : [];
        $inside = array_values(array_filter(self::THRESHOLDS, static fn (int $threshold): bool => $daysLeft <= $threshold));
        $new = array_values(array_diff($inside, $sent));

        if ($new === []) {
            return null;
        }

        return ['sent' => array_values(array_unique([...$sent, ...$inside])), 'ended' => in_array(0, $new, true)];
    }

    /** The psychologists of the provider hear which battery stopped issuing by itself because the licence of the one who answers for it ended. */
    private function pauseAutoIssue(PsychologistLicences $row): void
    {
        $batteries = $this->notices->autoIssueBatteriesOf($row->psychologistId);

        if ($batteries === []) {
            return;
        }

        $recipients = $this->notices->activeUserIdsOfProvider($row->providerId);

        foreach ($batteries as $battery) {
            $this->notifier->send($recipients, AssessmentNotificationType::AUTO_ISSUE_PAUSED, ['test_id' => $battery['id'], 'test_title' => $battery['title']]);
        }
    }
}
